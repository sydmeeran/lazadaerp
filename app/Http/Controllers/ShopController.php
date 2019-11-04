<?php

namespace App\Http\Controllers;

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

class ShopController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $breadcrumbs = [
            ['link'=>"/",'name'=>"Home"],['link'=> action('ShopController@index'), 'name'=>"Shop List"], ['name'=>"Shops"]
        ];
        if ( request()->ajax()) {
           $shop = Shop::where('user_id', $request->user()->id);

           $shop->orderBy('updated_at', 'desc');
            return Datatables::eloquent($shop)
            ->addColumn('total_shipped', function(Shop $shop) {
                $orders = $shop->getTotalOrders('shipped');
                return '<div class="chip chip-success"><div class="chip-body"><div class="chip-text">'. 
                isset($orders['data']['count']) ?  $orders['data']['count'] : 0 .'</div></div></div>';
             })  
            ->rawColumns(['total_shipped'])
            ->make(true);
        }

        return view('shop.index', [
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $breadcrumbs = [
          ['link'=>"/",'name'=>"Home"], ['link'=> route('shop.create'),'name'=>"Add Shop"], ['name'=>"Shop"]
        ];
        return view('shop.create', [
          'breadcrumbs' => $breadcrumbs
        ]);
    }

    public function form(){
        $breadcrumbs = [
          ['link'=>"/",'name'=>"Home"], ['link'=> route('shop.create'),'name'=>"Add Shop"], ['name'=>"Shop"]
        ];
        return view('shop.form', [
          'breadcrumbs' => $breadcrumbs
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), ['name' => ['required'], 'short_name' => 'required']);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
            }
        try {
            DB::beginTransaction();
            $data = $request->all();
            $client = new LazopClient("https://auth.lazada.com/rest", Lazop::get_api_key(), Lazop::get_api_secret());
            $r = new LazopRequest("/auth/token/create");
            $r->addApiParam("code", $data['code']);
            $response = $client->execute($r);

            $responseData = json_decode($response, true);
        
            if(! array_key_exists('account', $responseData)){
                $output = ['success' => 0,
                    'msg' => 'Sorry something went wrong, please try again later. [ '. $responseData['message'] .' ]'
                ];
                return response()->json($output);
            }
            if(Shop::where('email', $responseData['account'])->count() >= 1){
                $output = ['success' => 0,
                        'msg' => 'Shop '. $responseData['account'] .' already exists!',
                        'redirect' => action('ShopController@index')
                    ];
                return response()->json($output);
            }
            $data['refresh_token'] = $responseData['refresh_token'];
            $data['access_token'] = $responseData['access_token'];
            $data['email'] = $responseData['account'];
            $data['expires_in'] = Carbon::now()->addDays(6);
            $data['user_id'] = $request->user()->id;
            Shop::create($data);
            DB::commit();
            $output = ['success' => 1,
                        'msg' => 'Shop added successfully!',
                        'redirect' => action('ShopController@index')
                    ];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). " Line:" . $e->getLine(). " Message:" . $e->getMessage());
            $output = ['success' => 0,
                        'msg' => env('APP_DEBUG') ? $e->getMessage() : 'Sorry something went wrong, please try again later.'
                    ];
             DB::rollBack();
        }
        return response()->json($output);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Shop  $shop
     * @return \Illuminate\Http\Response
     */
    public function show(Shop $shop)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Shop  $shop
     * @return \Illuminate\Http\Response
     */
    public function edit(Shop $shop)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Shop  $shop
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Shop $shop)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Shop  $shop
     * @return \Illuminate\Http\Response
     */
    public function destroy(Shop $shop)
    {
        //
    }
}
