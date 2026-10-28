<?php

namespace App\Notifications;

use App\Models\Purchase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification
{
    public function __construct(public Purchase $purchase) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Оплата курса подтверждена')
            ->greeting('Здравствуйте, '.$notifiable->name.'!')
            ->line('Оплата курса «'.$this->purchase->course->title.'» подтверждена.')
            ->line('Сумма: '.number_format($this->purchase->amount, 0, ',', ' ').' UZS.')
            ->line('Курс доступен в вашем личном кабинете.')
            ->salutation('Желаем успешного обучения!');
    }
}
