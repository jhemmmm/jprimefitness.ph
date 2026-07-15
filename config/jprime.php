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
    */

    'cash_drawer' => (bool) env('CASH_DRAWER', true),

];
