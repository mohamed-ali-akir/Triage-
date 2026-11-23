package fr.triage.mobile.ui.ticket

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import fr.triage.mobile.core.api.MessageDto
import fr.triage.mobile.core.api.TicketDto
import fr.triage.mobile.ui.Badge
import fr.triage.mobile.ui.LateBadge
import fr.triage.mobile.ui.PriorityBadge
import fr.triage.mobile.ui.StatusBadge
import fr.triage.mobile.ui.formatDate

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TicketDetailScreen(
    viewModel: TicketDetailViewModel,
    onBack: () -> Unit,
    onSessionExpired: () -> Unit,
) {
    val state by viewModel.state.collectAsStateWithLifecycle()

    LaunchedEffect(state.sessionExpired) {
        if (state.sessionExpired) {
            onSessionExpired()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(state.ticket?.let { "Ticket #${it.id}" } ?: "Ticket") },
                navigationIcon = { TextButton(onClick = onBack) { Text("Retour") } },
                actions = { TextButton(onClick = viewModel::load) { Text("Actualiser") } },
            )
        },
    ) { padding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .imePadding(),
        ) {
            val ticket = state.ticket

            when {
                ticket == null && state.isLoading ->
                    CircularProgressIndicator(Modifier.align(Alignment.Center))

                ticket == null ->
                    Text(
                        text = state.error ?: "Ce ticket est introuvable.",
                        color = MaterialTheme.colorScheme.error,
                        modifier = Modifier
                            .align(Alignment.Center)
                            .padding(24.dp),
                    )

                else -> TicketContent(
                    ticket = ticket,
                    state = state,
                    onStatusClick = viewModel::changeStatus,
                    onDraftChange = viewModel::onDraftChange,
                    onInternalChange = viewModel::onInternalChange,
                    onSend = viewModel::sendMessage,
                )
            }
        }
    }
}

@Composable
private fun TicketContent(
    ticket: TicketDto,
    state: TicketDetailUiState,
    onStatusClick: (String) -> Unit,
    onDraftChange: (String) -> Unit,
    onInternalChange: (Boolean) -> Unit,
    onSend: () -> Unit,
) {
    LazyColumn(
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.fillMaxSize(),
    ) {
        item { Summary(ticket) }

        state.error?.let { message ->
            item { Text(message, color = MaterialTheme.colorScheme.error) }
        }

        // One button per status the server allows for this technician: the state
        // machine lives on the server, the application only displays its answer.
        if (ticket.allowedStatuses.isNotEmpty()) {
            item {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("Changer le statut", style = MaterialTheme.typography.titleSmall)

                    ticket.allowedStatuses.forEach { status ->
                        OutlinedButton(
                            onClick = { onStatusClick(status.value) },
                            enabled = !state.isWorking,
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Text(status.label)
                        }
                    }
                }
            }
        }

        item { Text("Discussion", style = MaterialTheme.typography.titleSmall) }

        if (ticket.messages.isEmpty()) {
            item { Text("Aucun message pour le moment.", color = MaterialTheme.colorScheme.secondary) }
        }

        items(ticket.messages, key = { it.id }) { message -> MessageCard(message) }

        if (ticket.canReply) {
            item {
                ReplyForm(
                    draft = state.draft,
                    isInternal = state.isInternal,
                    isWorking = state.isWorking,
                    onDraftChange = onDraftChange,
                    onInternalChange = onInternalChange,
                    onSend = onSend,
                )
            }
        }
    }
}

@Composable
private fun Summary(ticket: TicketDto) {
    Card(
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        modifier = Modifier.fillMaxWidth(),
    ) {
        Column(Modifier.padding(16.dp)) {
            Text(ticket.title, style = MaterialTheme.typography.titleLarge)

            Spacer(Modifier.height(8.dp))

            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                StatusBadge(ticket.status.label)
                ticket.priority?.let { PriorityBadge(it.value, it.label) }
                if (ticket.isLate) {
                    LateBadge()
                }
            }

            Spacer(Modifier.height(12.dp))

            Text(
                text = listOfNotNull(
                    "Demandeur : ${ticket.requester.name}",
                    ticket.category?.let { "Catégorie : ${it.name}" },
                    "Créé le ${formatDate(ticket.createdAt)}",
                    ticket.resolveDeadline?.let { "Échéance : ${formatDate(it)}" },
                ).joinToString("\n"),
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.secondary,
            )

            ticket.description?.let { description ->
                Spacer(Modifier.height(12.dp))
                Text(description, style = MaterialTheme.typography.bodyMedium)
            }
        }
    }
}

@Composable
private fun MessageCard(message: MessageDto) {
    // An internal note is tinted, as on the web: the requester never sees it.
    val background = if (message.isInternal) Color(0xFFFFFBEB) else MaterialTheme.colorScheme.surface

    Card(colors = CardDefaults.cardColors(containerColor = background), modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(12.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                Text(message.author.name, style = MaterialTheme.typography.labelLarge)
                Text(
                    formatDate(message.createdAt),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.secondary,
                )
                if (message.isInternal) {
                    Badge("Note interne", background = Color(0xFFFEF3C7), content = Color(0xFF92400E))
                }
            }

            Spacer(Modifier.height(4.dp))

            Text(message.body, style = MaterialTheme.typography.bodyMedium)
        }
    }
}

@Composable
private fun ReplyForm(
    draft: String,
    isInternal: Boolean,
    isWorking: Boolean,
    onDraftChange: (String) -> Unit,
    onInternalChange: (Boolean) -> Unit,
    onSend: () -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        OutlinedTextField(
            value = draft,
            onValueChange = onDraftChange,
            label = { Text("Votre message") },
            minLines = 3,
            modifier = Modifier.fillMaxWidth(),
        )

        Row(verticalAlignment = Alignment.CenterVertically) {
            Checkbox(checked = isInternal, onCheckedChange = onInternalChange)
            Text("Note interne (invisible du demandeur)", style = MaterialTheme.typography.bodyMedium)
        }

        Button(
            onClick = onSend,
            enabled = !isWorking && draft.isNotBlank(),
            modifier = Modifier.fillMaxWidth(),
        ) {
            Text(if (isWorking) "Envoi…" else "Envoyer")
        }
    }
}
