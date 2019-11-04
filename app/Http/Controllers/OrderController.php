<?php

namespace App\Http\Controllers;

use App\Order;
use App\Shop;
use Illuminate\Http\Request;
use App\Lazop;
use Carbon\Carbon;
use App\Library\lazada\LazopRequest;
use App\Library\lazada\LazopClient;
use App\Library\lazada\UrlConstants;
use App\Http\Controllers\Controller;
use App\Utilities;
use Validator;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $breadcrumbs = [
            ['link'=>"/",'name'=>"Home"],['link'=> action('OrderController@index'), 'name'=>"Orders List"], ['name'=>"Orders"]
        ];
        $all_shops = Shop::where('user_id', $request->user()->id)->orderBy('updated_at', 'desc')->get();
        $statuses = Shop::$statuses;
        
    if ( request()->ajax()) {
           $shops = Shop::where('user_id', $request->user()->id)->orderBy('updated_at', 'desc');
           if($request->get('shop', 'all') != 'all'){
                $shops->where('id', $request->get('shop'));
           }
           $statuses = $request->get('status', ['shipped']);
           $all_orders = [];
           foreach($shops->get() as $shop){
                if($request->get('search')){
                        $orders = $shop->searchOrderID($request->get('search'));
                        $all_orders = array_merge_recursive($orders, $all_orders);
                }else{
                    foreach($statuses as $status){
                        $orders = $shop->getTotalOrders($status);
                        $all_orders = array_merge_recursive($orders, $all_orders);
                    }
                }
            }
            $all_orders = isset($all_orders['data']['orders']) ? $all_orders : Shop::dummyOrder();
            return Datatables::collection($all_orders['data']['orders'])
                    ->filter(function ($keyword){})
                    ->setTotalRecords(is_numeric($all_orders['data']['count']) ? $all_orders['data']['count'] : array_sum($all_orders['data']['count']))
                    ->setFilteredRecords(is_numeric($all_orders['data']['count']) ? $all_orders['data']['count'] : array_sum($all_orders['data']['count']))
                    ->addColumn('actions', function($order) {
                            return $html = Shop::getActionsDropdown($order);
                                })
                    ->addColumn('created_at', function($order) {
                            return Utilities::format_date($order['created_at'], 'M d, Y H:i');
                                })
                    ->skipPaging(true)
                    ->rawColumns(['actions'])
                    ->make(true);
    }
        
        return view('order.index', [
            'breadcrumbs' => $breadcrumbs,
            'all_shops' => $all_shops,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(Order $order)
    {
        //
    }

    public function cancel(Request $request){
        $order_id = $request->get('order_id');
        if($order_id == null){
            $output = ['success' => 0,
                        'msg' => 'Invalid Order ID. Please try again later'
                    ];
            return response()->json($output);
        }
        try {
            $msg = $request->get('input');
            $shop = Shop::findOrFail($request->get('shop_id'));
            $items = $shop->getOrderItems($order_id);
            $item_ids = $shop->getItemIds($items);  
            $result = $shop->cancel($item_ids, $msg);
            if(isset($result['message'])){
                $output = ['success' => 0,
                        'msg' => $result['message'],
                    ];
            }else{
                $output = ['success' => 1,
                    'msg' => 'Orders '. $order_id .' Canceled',
                ];
            }
            
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). " Line:" . $e->getLine(). " Message:" . $e->getMessage());
            $output = ['success' => 0,
                        'msg' => env('APP_DEBUG') ? $e->getMessage() : 'Sorry something went wrong, please try again later.'
                    ];
             DB::rollBack();
        }
        return response()->json($output);
    }

    public function readyToShip(Request $request){
        $order_id = $request->get('order_id');
        if($order_id == null){
            $output = ['success' => 0,
                        'msg' => 'Invalid Order ID. Please try again later'
                    ];
            return response()->json($output);
        }
        try {
            $shop = Shop::findOrFail($request->get('shop_id'));
            $items = $shop->getOrderItems($order_id);
            $item_ids = $shop->getItemIds($items);
            $result = $shop->readyToShip($item_ids);
            if(isset($result['message'])){
                $output = ['success' => 0,
                        'msg' => $result['message'],
                    ];
            }else{
                $output = ['success' => 1,
                    'msg' => 'Orders '. $order_id .' Ready to Ship',
                ];
            }
            
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). " Line:" . $e->getLine(). " Message:" . $e->getMessage());
            $output = ['success' => 0,
                        'msg' => env('APP_DEBUG') ? $e->getMessage() : 'Sorry something went wrong, please try again later.'
                    ];
             DB::rollBack();
        }
        return response()->json($output);
    }
}
