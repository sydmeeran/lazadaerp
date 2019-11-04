<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Lazop;
use App\Utilities;
use Carbon\Carbon;
use App\Library\lazada\LazopRequest;
use App\Library\lazada\LazopClient;
use App\Library\lazada\UrlConstants;

class Shop extends Model
{
    protected $table = 'shop';

    protected $fillable = ['user_id', 'name', 'short_name', 'refresh_token', 'access_token', 'expires_in', 'active', 'email'];

    public static $statuses = [
              'shipped', 'ready_to_ship', 'pending', 'delivered', 'returned', 'failed', 'unpaid', 'canceled', 
    ];

    public static function dummyOrder(){
        return ['data' => ['count' => [0], 'orders' => []], 'code' => [], 'request_id' => []];
    }

    public function getTotalOrders($status = null){
            $c = new LazopClient(UrlConstants::getPH(), Lazop::get_api_key(), Lazop::get_api_secret());
            $r = new LazopRequest('/orders/get','GET');
            $r->addApiParam('created_before', Carbon::now()->addYears(3)->format('c'));
            if($status){
                $r->addApiParam('status', $status);
            }
            $r->addApiParam('sort_direction','DESC');
            $r->addApiParam('update_after', Carbon::now()->subYears(3)->format('c'));
            $r->addApiParam('sort_by','updated_at');
            $result = $c->execute($r, $this->access_token);
            $data = json_decode($result, true);
            if(! isset($data['message'])){
                $data['data']['orders'] = array_map(function($data){
                    $str_status = ucwords(str_replace("_"," ", $data['statuses'][0]));
                    return array_merge($data, ['seller' => $this->name, 'status' => $str_status, 'seller_id' => $this->id]);
                }, $data['data']['orders']);
            }
            return $data;
    }

    public function searchOrderID($order_id){
            $c = new LazopClient(UrlConstants::getPH(), Lazop::get_api_key(), Lazop::get_api_secret());
            $r = new LazopRequest('/order/get','GET');
            $r->addApiParam("order_id", $order_id);
            $result = $c->execute($r, $this->access_token);
            $data = json_decode($result, true);
            if(! isset($data['message'])){
                $data['data']['status'] = ucwords(str_replace("_"," ", $data['data']['statuses'][0]));
                $data['data']['seller'] = $this->name;
                $data['data']['seller_id'] = $this->id;
            }else{
                return $this->dummyOrder();
            }
            $data['data']['orders'] = [$data['data']];
            $data['data']['count'] = [1];
            return $data;
    }

    public function getOrderItems($order_id){
        $c = new LazopClient(UrlConstants::getPH(), Lazop::get_api_key(), Lazop::get_api_secret());
        $r = new LazopRequest('/order/items/get','GET');
        $r->addApiParam('order_id', $order_id);
        $result =  $c->execute($r, $this->access_token);
        return json_decode($result, true);
    }

    public function getItemIds($items){
        $item_ids = [];
        foreach($items['data'] as $item){
            $item_ids[] = $item['order_item_id'];
        }
        return $item_ids;
    }

    public function readyToShip($item_ids){
        $item_ids = '[' . implode(', ', $item_ids) . ']';
        $c = new LazopClient(UrlConstants::getPH(), Lazop::get_api_key(), Lazop::get_api_secret());
        $r = new LazopRequest('/order/rts');
        $r->addApiParam('delivery_type','dropship');
        $r->addApiParam('order_item_ids', $item_ids);
        $r->addApiParam('shipment_provider','Aramax');
        $r->addApiParam('tracking_number','12345678');
        $result = $c->execute($r, $this->access_token);
        return json_decode($result, true);
    }

    public function cancel($item_ids, $msg = null){
        $c = new LazopClient(UrlConstants::getPH(), Lazop::get_api_key(), Lazop::get_api_secret());
        // dd($this->access_token);
        $r = new LazopRequest('/order/cancel');
        $r->addApiParam('reason_detail', $msg);
        $r->addApiParam('reason_id','15');
        $r->addApiParam('order_item_id',$item_ids[0]);
        $result = $c->execute($r, $this->access_token);
        return json_decode($result, true);
    }

    public static function getActionsDropdown($order){
        $nextAction = self::getNextAction($order);
        $disabled = ['print_shipping_label' => 'disabled', 'cancel' => 'disabled', 'ready_to_ship' => 'disabled'];
        if($order['statuses'][0] == 'ready_to_ship'){
            $disabled['print_shipping_label'] = '';
            $disabled['cancel'] = '';
        }else if($order['statuses'][0] == 'pending'){
            $disabled['print_shipping_label'] = '';
            $disabled['cancel'] = '';
            $disabled['ready_to_ship'] = '';
        }else if($order['statuses'][0] == 'shipped'){
            $disabled['print_shipping_label'] = '';
        }
        $dropdown = '<div class="btn-group dropup mr-1 mb-1">
                    '. $nextAction .'
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"aria-haspopup="true" aria-expanded="false">
                    <span class="sr-only">Toggle Dropdown</span></button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item confirm '. $disabled['ready_to_ship'] .'" href="#" data-href="'. action('OrderController@readyToShip', ['order_id' => $order['order_id'], 'shop_id' => $order['seller_id']]) .'" data-text="Are you sure to mark '. $order['order_id'] .' as ready to ship?" data-text="This Action is irreversible."><i class="fa fa-truck aria-hidden="true""></i> Ready to Ship</a>
                        <a class="dropdown-item '. $disabled['print_shipping_label'] .'" href="#"><i class="fa fa-print aria-hidden="true""></i> Print Shipping Label</a>
                        <a class="dropdown-item confirm '. $disabled['cancel'] .'" href="#" data-href="'. action('OrderController@cancel', ['order_id' => $order['order_id'], 'shop_id' => $order['seller_id']]) .'" data-text="Are you sure to mark '. $order['order_id'] .' as canceled?" data-text="This Action is irreversible." data-input="textarea" data-placeholder="Type your reason here..."><i class="fa fa-window-close-o aria-hidden="true""></i> Cancel Order</a>
                    </div></div>';
        return $dropdown;
    }

    public static function getNextAction($order){
        $status = $order['statuses'][0];
        if($status == 'pending'){
            $btn = '<button type="button" class="btn btn-primary confirm" data-href="'. action('OrderController@readyToShip', ['order_id' => $order['order_id'], 'shop_id' => $order['seller_id']]) .'" data-text="Are you sure to mark '. $order['order_id'] .' as ready to ship?" data-text="This Action is irreversible.">Ready to Ship</button>';
        }else if($status == 'ready_to_ship'){
            $btn = '<button type="button" class="btn btn-primary">Print Shipping Label</button>';
        }else{
            $btn = '<button type="button" class="btn btn-primary">View detail</button>';
        }
        return $btn;
    }
}