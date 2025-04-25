<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('elerner-app', function () {
    return true;
});
