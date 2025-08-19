<?php

return [
    'terminals' => [
        1 => 'TERM-MTL',
        2 => 'TERM-OTT', 
        3 => 'TERM-TOR',
    ],
    
    'order_statuses' => [
        'pending' => 'Pending',
        'billing' => 'Billing',
        'quote' => 'Quote',
        'entered' => 'Entered',
        'dispatched' => 'Dispatched',
        'on_dock' => 'On Dock',
        'arrived_shipper' => 'Arrived Shipper',
        'picked_up' => 'Picked Up',
        'arrived_receiver' => 'Arrived Receiver',
        'delivered' => 'Delivered',
        'approved' => 'Approved',
        'billed' => 'Billed',
        'cancelled' => 'Cancelled'
    ],
    
    'order_types' => [
        1 => 'Order Entry',
        2 => 'Order Billing',
    ],
    
    // You can add more configs here
    'default_terminal' => 1,
    'active_terminals' => [1, 2, 3],
    'default_status' => 'entered',
    'default_order_type' => 1,
];