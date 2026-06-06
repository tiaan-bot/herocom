<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Ordering\Actions\AcceptOrderAction;
use App\Domain\Ordering\Actions\RejectOrderAction;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\ZohoPushStatus;
use App\Domain\Ordering\Events\OrderPlaced;
use App\Domain\Ordering\Exceptions\OrderException;
use App\Domain\Ordering\Listeners\PushOrderToZoho;
use App\Domain\Ordering\Models\Order;
use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->acceptAction(),
            $this->rejectAction(),
            $this->retryPushAction(),
        ];
    }

    private function acceptAction(): Action
    {
        return Action::make('accept')
            ->label('Accept')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Placed && $this->currentUser()->can('accept', $record))
            ->action(function (Order $record): void {
                app(AcceptOrderAction::class)->execute($record, $this->currentUser());
                Notification::make()->success()->title('Order accepted')->body('The reseller has been emailed.')->send();
            });
    }

    private function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Order $record): bool => $record->status === OrderStatus::Placed && $this->currentUser()->can('reject', $record))
            ->schema([
                Textarea::make('reason')->label('Reason for rejection')->required(),
            ])
            ->action(function (array $data, Order $record): void {
                app(RejectOrderAction::class)->execute($record, $this->currentUser(), $data['reason']);
                Notification::make()->success()->title('Order rejected')->body('The reseller has been emailed.')->send();
            });
    }

    private function retryPushAction(): Action
    {
        return Action::make('retryPush')
            ->label('Retry Zoho push')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->visible(fn (Order $record): bool => $record->zoho_push_status === ZohoPushStatus::Failed && $this->currentUser()->can('accept', $record))
            ->action(function (Order $record): void {
                try {
                    app(PushOrderToZoho::class)->handle(new OrderPlaced($record));
                } catch (ZohoException|OrderException $e) {
                    Notification::make()->danger()->title('Push failed again')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Order pushed to Zoho')->send();
            });
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
