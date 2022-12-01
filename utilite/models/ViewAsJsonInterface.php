<?php

namespace app\models;

interface ViewAsJsonInterface
{
    public function viewAsJson(bool $toJson = false);
}