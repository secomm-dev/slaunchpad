<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Boolfly\GiaoHangNhanh\Helper;

class OrderStatus
{
    public static function getStatusDescription($status): string
    {
        $statuses = [
            'ready_to_pick' => 'New order created',
            'picking' => 'Employee is picking up the items',
            'cancel' => 'Cancel order',
            'money_collect_picking' => 'Collecting payment from sender',
            'picked' => 'Items have been picked up by employee',
            'storing' => 'Items are currently stored in warehouse',
            'transporting' => 'Items are in transit',
            'sorting' => 'Items are being sorted',
            'delivering' => 'Employee is delivering to recipient',
            'money_collect_delivering' => 'Collecting payment from recipient',
            'delivered' => 'Items have been successfully delivered',
            'delivery_fail' => 'Failed delivery attempt',
            'waiting_to_return' => 'Waiting to return items to sender',
            'return' => 'Return items',
            'return_transporting' => 'Items for return are in transit',
            'return_sorting' => 'Items for return are being sorted',
            'returning' => 'Employee is returning items',
            'return_fail' => 'Failed return attempt',
            'returned' => 'Items have been successfully returned',
            'exception' => 'Order does not fit into the process',
            'damage' => 'Items are damaged',
            'lost' => 'Items are lost',
        ];

        return $statuses[$status] ?? 'N/A';
    }
}
