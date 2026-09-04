<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Book;
use App\Models\Loan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->isStaff()) {
            $totalBooks = Book::query()->count();
            $borrowed = Loan::query()->active()->count();

            return Inertia::render('Dashboard', [
                'role' => $user->role->value,
                'stats' => [
                    'totalBooks' => $totalBooks,
                    'available' => $totalBooks - $borrowed,
                    'borrowed' => $borrowed,
                    'overdue' => Loan::query()->overdue()->count(),
                ],
                'recentAuditEvents' => $user->isAdmin() || $user->isLibrarian()
                    ? AuditEvent::query()->with('user')->latest('created_at')->limit(8)->get()
                        ->map(fn (AuditEvent $event) => [
                            'id' => $event->id,
                            'description' => $event->describe(),
                            'created_at' => $event->created_at,
                        ])
                    : [],
            ]);
        }

        return Inertia::render('Dashboard', [
            'role' => $user->role->value,
            'stats' => [
                'activeLoans' => Loan::query()->where('user_id', $user->id)->active()->count(),
                'overdueLoans' => Loan::query()->where('user_id', $user->id)->overdue()->count(),
                'totalBooks' => Book::query()->count(),
                'available' => Book::query()->available()->count(),
            ],
            'myActiveLoans' => Loan::query()
                ->where('user_id', $user->id)
                ->active()
                ->with('book')
                ->orderBy('due_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
