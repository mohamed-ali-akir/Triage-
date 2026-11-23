<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Support\Audit;
use App\Support\Csv;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketExportController extends Controller
{
    private const HEADERS = [
        'Numéro', 'Créé le', 'Titre', 'Statut', 'Priorité', 'Catégorie', 'Demandeur', 'Service',
        'Technicien', 'Pris en charge le', 'Résolu le', 'Clos le', 'Résolu dans les délais', 'Satisfaction',
    ];

    /**
     * Every ticket as a CSV file, for a spreadsheet or a reporting tool.
     * The descriptions and the messages are not exported: only what a report needs.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        Audit::record('tickets.exported', ['by' => $request->user()->id, 'count' => Ticket::count()]);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');

            // The byte order mark and the semicolon make the file open correctly in a French Excel.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, self::HEADERS, ';');

            // lazy(): tickets are read by small groups, so a large export does not fill the memory.
            foreach (Ticket::query()->with(['category', 'requester.department', 'assignee'])->orderBy('id')->lazy(200) as $ticket) {
                fputcsv($output, array_map(Csv::cell(...), $this->row($ticket)), ';');
            }

            fclose($output);
        }, 'tickets-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<string|int|null>
     */
    private function row(Ticket $ticket): array
    {
        $onTime = $ticket->resolved_at === null || $ticket->resolveDeadline() === null
            ? ''
            : ($ticket->resolved_at->lessThanOrEqualTo($ticket->resolveDeadline()) ? 'oui' : 'non');

        return [
            $ticket->id,
            $ticket->created_at?->format('d/m/Y H:i'),
            $ticket->title,
            $ticket->status->label(),
            $ticket->priority?->label(),
            $ticket->category?->name,
            $ticket->requester->name,
            $ticket->requester->department?->name,
            $ticket->assignee?->name,
            $ticket->taken_at?->format('d/m/Y H:i'),
            $ticket->resolved_at?->format('d/m/Y H:i'),
            $ticket->closed_at?->format('d/m/Y H:i'),
            $onTime,
            $ticket->satisfaction_score,
        ];
    }
}
