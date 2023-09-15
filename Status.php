<?php

namespace NW\WebService\References\Operations\Notification;

class Status
{
    public static function getName(int $id): string
    {
        $a = ['Completed', 'Pending','Rejected'];

        return $a[$id];
    }
}
