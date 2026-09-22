<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature toggles
    |--------------------------------------------------------------------------
    |
    | cash_drawer: enables the Cash Drawer page, its routes, and the automatic
    | ledger entries recorded from cash sales and cash payouts. Disable with
    | CASH_DRAWER=false in .env.
    |
    | attendance_auto_checkout_hours: members/walk-ins still checked in after
    | this many hours are checked out automatically (hourly schedule). Walk-ins
    | have no kiosk time-out and members often forget theirs.
    |
    */

    'cash_drawer' => (bool) env('CASH_DRAWER', true),

    'attendance_auto_checkout_hours' => (int) env('ATTENDANCE_AUTO_CHECKOUT_HOURS', 4),

];
