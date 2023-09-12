<?php

namespace NW\WebService\References\Operations\Notification;

class MessagesClient
{
    /**
     * @param string $emailFrom
     * @param string $emailTo
     * @param string $subject
     * @param string $messageHtml
     * @param int $reseller_id
     * @param $client_id
     * @param $event_status
     * @param $data_differences_to
     * @return true
     * @throws \Exception
     */
    public static function sendEmailMessage(string $emailFrom, string $emailTo, string $subject, string $messageHtml, int $reseller_id, $client_id = null, $event_status = null, $data_differences_to = null)
    {
        // Подготовка заголовков письма
        $headers = "From: $emailFrom" . "\r\n";
        $headers .= "Reply-To: $emailFrom" . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8" . "\r\n";

        // Отправка письма
        if (mail($emailTo, $subject, $messageHtml, $headers)) {
            return true;
        } else {
            // Ошибка при отправке письма
            throw new \Exception('Failed to send email', 500);
        }

    }
}