<?php

namespace NW\WebService\References\Operations\Notification;

class Employee extends Contractor
{
    public static function getById(int $resellerId): Contractor
    {
        return parent::getById($resellerId); // TODO: Change the autogenerated stub
    }
}