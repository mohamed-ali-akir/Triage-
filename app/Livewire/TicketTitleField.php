<?php

namespace App\Livewire;

use App\Services\Kb\KbSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The title field of the "new ticket" form. While the employee types, it looks
 * for help-center articles on the same subject: a problem solved by an article
 * is a ticket nobody has to write, read or answer.
 */
class TicketTitleField extends Component
{
    // Below this length a title says too little to look for articles.
    private const MIN_LENGTH = 5;

    public string $title = '';

    public function mount(string $title = ''): void
    {
        $this->title = $title;
    }

    public function render(KbSearch $search): View
    {
        $query = Str::limit(trim($this->title), 150, '');

        return view('livewire.ticket-title-field', [
            // Help-center articles only: an internal procedure is never suggested to an employee.
            'articles' => mb_strlen($query) < self::MIN_LENGTH
                ? collect()
                : $search->search($query, limit: 3, publicOnly: true),
        ]);
    }
}
