<?php
// app/Console/Commands/SendSubscriptionExpirationNotifications.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription; // Ton modèle d'abonnement
use App\Models\Notification;
use Carbon\Carbon;

class SendSubscriptionExpirationNotifications extends Command
{
    protected $signature = 'subscriptions:notify-expiring';

    protected $description = 'Notifier les utilisateurs dont l\'abonnement expire bientôt';

    public function handle()
    {
        $today = Carbon::today();

        // Récupérer tous les abonnements expirant dans les 7 prochains jours
        $subscriptions = Subscription::whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $today->copy()->addDays(7))
            ->get();

        foreach ($subscriptions as $subscription) {
            $daysLeft = $today->diffInDays(Carbon::parse($subscription->end_date));

            $userId = $subscription->user_id;

            // Vérifie si une notification existe déjà aujourd’hui
            $exists = Notification::where('user', $userId)
                ->where('type', 'abonnement')
                ->whereDate('created_at', $today)
                ->exists();

            if (!$exists) {
                Notification::create([
                    'user' => $userId,
                    'message' => "Votre abonnement MySpace expire dans {$daysLeft} jour(s), le " . Carbon::parse($subscription->end_date)->format('d/m/Y') . ".",
                    'type' => 'abonnement',
                ]);

                $this->info("Notification envoyée à l'utilisateur ID {$userId}");
            }
        }

        return Command::SUCCESS;
    }
}
