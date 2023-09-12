<?php

namespace NW\WebService\References\Operations\Notification;

// Функция __() используется для перевода строк
function __(string $string, array $templateData, int $reseller_id): string
{
    return $string;
}

class ReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    /**
     * Выполняет операцию отправки уведомлений.
     *
     * @throws \Exception
     */
    public function doOperation(): array
    {
        // Получение данных из запроса
        $data = $this->getRequest('data');

        if ($data === null) {
            throw new \Exception('Empty or Wrong data', 400);
        }

        // Извлечение необходимых значений из данных
        $reseller_id = (int)$data['resellerId'];
        $notificationType = (int)$data['notificationType'];
        $client_id = (int)$data['clientId'];
        $creator_id = (int)$data['creatorId'];
        $expert_id = (int)$data['expertId'];

        // Извлечение данных о различиях
        $data_differences = $data['differences'];
        $data_differences_from = (int)$data_differences['from'];
        $data_differences_to = (int)$data_differences['to'];

        // массива для результатов уведомлений
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];

        // Проверка наличия значения reseller_id
        if (empty($reseller_id)) {
            throw new \Exception('Empty resellerId', 400);
        }

        // Проверка наличия значения notificationType
        if (empty($notification_type)) {
            throw new \Exception('Empty notificationType', 400);
        }

        // Получение данных о реселлере
        $reseller = $this->getReseller($reseller_id);

        // Получение данных о клиенте
        $client = $this->getClient($client_id, $reseller->id);

        // Получение данных о создателе
        $creator = $this->getCreator($creator_id);

        // Получение данных об эксперте
        $expert = $this->getExpert($expert_id);

        // Определение сообщения о различиях в зависимости от типа уведомления
        $differences = '';
        if ($notification_type === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', [], $reseller_id);
        } elseif ($notification_type === self::TYPE_CHANGE && !empty($data_differences)) {
            $differences = __('PositionStatusHasChanged',
                ['FROM' => Status::getName($data_differences_from), 'TO' => Status::getName($data_differences_to)],
                $reseller_id);
        }

        // Формирование массива для шаблона уведомления
        $templateData = [
            'COMPLAINT_ID' => (int)$data['complaintId'],
            'COMPLAINT_NUMBER' => (string)$data['complaintNumber'],
            'CREATOR_ID' => $creator_id,
            'CREATOR_NAME' => $creator->getFullName() ?? $creator->name,
            'EXPERT_ID' => $expert_id,
            'EXPERT_NAME' => $expert->getFullName() ?? $expert->name,
            'CLIENT_ID' => $client_id,
            'CLIENT_NAME' => $client->getFullName() ?? $client->name,
            'CONSUMPTION_ID' => (int)$data['consumptionId'],
            'CONSUMPTION_NUMBER' => (string)$data['consumptionNumber'],
            'AGREEMENT_NUMBER' => (string)$data['agreementNumber'],
            'DATE' => (string)$data['date'],
            'DIFFERENCES' => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        // Получение форму email реселлера
        $emailFrom = $this->getResellerEmailFrom($reseller_id);
        // Получаем email сотрудников из настроек
        $emails = $this->getEmailsByPermit($reseller_id, 'tsGoodsReturn');

        // Отправка email сотрудникам
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                $is_sent = $this->sendEmailToEmployee($emailFrom, $email, $templateData, $reseller_id);
                if ($is_sent) {
                    $result['notificationEmployeeByEmail'] = true;
                }
            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notification_type === self::TYPE_CHANGE && !empty($data_differences_to)) {
            if (!empty($emailFrom) && !empty($client->email)) {
                $is_sent = $this->sendEmailtToClient($emailFrom, $client, $templateData, $reseller_id, $data_differences_to);
                if ($is_sent) {
                    $result['notificationClientByEmail'] = true;
                }
            }

            // Отправка SMS клиенту
            if (!empty($client->mobile)) {
                $is_sent_sms = $this->sendSMSToClient($client, $templateData, $reseller_id, $data_differences_to);
                if ($is_sent_sms['success'] === true) {
                    $result['notificationClientBySms']['isSent'] = true;
                } else {
                    $result['notificationClientBySms']['message'] = $is_sent_sms['message'];
                }
            }
        }

        return $result;
    }

    /**
     * @param string $string
     * @return mixed|null
     */
    private function getRequest(string $string)
    {
        // Для тестирования
        $data = [
            'resellerId' => 1,
            'notificationType' => 1,
            'clientId' => 1,
            'creatorId' => 1,
            'expertId' => 1,
            'complaintId' => 1,
            'complaintNumber' => 1,
            'consumptionId' => 1,
            'consumptionNumber' => 1,
            'agreementNumber' => 1,
            'date' => 1,
            'differences' => [
                'from' => 1,
                'to' => 1,
            ],
        ];

        $data = $_POST['data'];

        // Валидация и очистка данных (ждем JSON)
        if (is_string($data)) {
            $decodedData = json_decode($data, true);

            if ($decodedData !== null) {
                // Данные успешно декодированы из JSON
                return $decodedData;
            }
        }

        return null;
    }

    private function getResellerEmailFrom(int $reseller_id)
    {
        return "emailForm for reseller {$reseller_id}";
    }

    private function getEmailsByPermit(int $reseller_id, string $string)
    {
        return [
            "test_reseller_1_{$reseller_id}_{$string}@example.com",
            "test_reseller_2_{$reseller_id}_{$string}@example.com",
            "test_reseller_3_{$reseller_id}_{$string}@example.com",
        ];
    }

    /**
     * @param int $reseller_id
     * @return mixed
     * @throws \Exception
     */
    private function getReseller(int $reseller_id)
    {
        $reseller = Seller::getById($reseller_id);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 400);
        }

        return $reseller;
    }

    /**
     * @param int $client_id
     * @param int $reseller_id
     * @return mixed
     * @throws \Exception
     */
    private function getClient(int $client_id, int $reseller_id)
    {
        $client = Contractor::getById($client_id);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->seller->id !== $reseller_id) {
            throw new \Exception('Client not found!', 400);
        }
        return $client;
    }

    /**
     * @param mixed $creator_id
     * @return mixed
     * @throws \Exception
     */
    private function getCreator(mixed $creator_id)
    {
        $creator = Employee::getById($creator_id);
        if ($creator === null) {
            throw new \Exception('Creator not found!', 400);
        }

        return $creator;
    }

    /**
     * @param int $expert_id
     * @return mixed
     * @throws \Exception
     */
    private function getExpert(int $expert_id)
    {
        $expert = Employee::getById($expert_id);
        if ($expert === null) {
            throw new \Exception('Expert not found!', 400);
        }

        return $expert;
    }

    private function sendEmailToEmployee(string $emailFrom, string $email, array $templateData, int $reseller_id)
    {
        return MessagesClient::sendEmailMessage(
            $emailFrom,
            $email,
            __('complaintEmployeeEmailSubject', $templateData, $reseller_id),
            __('complaintEmployeeEmailBody', $templateData, $reseller_id),
            $reseller_id,
            null,
            NotificationEvents::CHANGE_RETURN_STATUS
        );
    }

    /**
     * @param string $emailFrom
     * @param mixed $client
     * @param array $templateData
     * @param int $reseller_id
     * @param int $data_differences_to
     * @return true
     * @throws \Exception
     */
    private function sendEmailtToClient(string $emailFrom, mixed $client, array $templateData, int $reseller_id, int $data_differences_to)
    {
        return MessagesClient::sendEmailMessage(
            $emailFrom,
            $client->email,
            __('complaintClientEmailSubject', $templateData, $reseller_id),
            __('complaintClientEmailBody', $templateData, $reseller_id),
            $reseller_id,
            $client->id,
            NotificationEvents::CHANGE_RETURN_STATUS,
            $data_differences_to
        );
    }

    /**
     * @param mixed $client
     * @param array $templateData
     * @param int $reseller_id
     * @param int $data_differences_to
     * @return mixed
     */
    private function sendSMSToClient(mixed $client, array $templateData, int $reseller_id, int $data_differences_to)
    {
        return NotificationManager::send($reseller_id, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, $data_differences_to, $templateData);
    }
}