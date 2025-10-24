<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
// use Validator;
use DB;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Illuminate\Validation\Rule;
use Razorpay\Api\Api;
use Jenssegers\Agent\Agent;
use Intervention\Image\ImageManagerStatic as Image;
use IEXBase\TronAPI\Tron;
use App\lib\Image as OC_Image;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MemberController extends Controller {

    public function __construct() {
        Cache::flush();
        header("Pragma: no-cache");
        header("Cache-Control: no-cache");
        header("Expires: 0");
        $this->base_url = env('APP_URL');
        $this->profile_img_size_array = array(100 => 100);

        $data = DB::table('web_config')
                ->get();

        foreach ($data as $row) {
            $this->system_config[$row->web_config_name] = $row->web_config_value;
        }

        $this->timezone = $this->system_config['timezone'];       

        if ($this->system_config['under_maintenance'] == '1') {
            $array['message'] = '<h1>Under Maintenance</h1>';
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Update room ID and password for a specific match
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Get room details for a specific match
     * 
     * @param int $matchId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMatchRoomDetails($matchId) {
        try {
            $match = DB::table('matches')
                ->select('m_id as match_id', 'roomid', 'room_pass')
                ->where('m_id', $matchId)
                ->first();

            if (!$match) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Match not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $match
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update room ID and password for a specific match
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMatchRoomDetails(Request $request) {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'match_id' => 'required|exists:matches,m_id',
            'roomid' => 'required|string|max:255',
            'room_pass' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update the match record with room details
            $affected = DB::table('matches')
                ->where('m_id', $request->match_id)
                ->update([
                    'roomid' => $request->roomid,
                    'room_pass' => $request->room_pass
                ]);

            if ($affected) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Room details updated successfully',
                    'data' => [
                        'match_id' => $request->match_id,
                        'roomid' => $request->roomid,
                        'room_pass' => $request->room_pass
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to update room details',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the status of a match report
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMatchReportStatus(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'report_id' => 'required|integer|exists:match_reports,report_id',
            'status' => 'required|string|in:pending,under_review,cleared,suspended',
            'admin_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [
                'status' => $request->status,
                'updated_at' => \Carbon\Carbon::now(),
                'action_date' => \Carbon\Carbon::now(),
            ];
            if ($request->has('admin_note')) {
                $updateData['admin_note'] = $request->admin_note;
            }

            $updated = DB::table('match_reports')
                ->where('report_id', $request->report_id)
                ->update($updateData);

            if ($updated) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Report status updated successfully',
                    'report_id' => $request->report_id,
                    'new_status' => $request->status
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update report status'
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Failed to update match report status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update report status. Please try again later.'
            ], 500);
        }
    }

    public function demo(Request $request) {        
        $file = $request->file('image');
        // Define our destinations
        
        $destinationPath = substr(base_path(), 0, strrpos(base_path(), '/')) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR ;
        $destinationPathThumb = $destinationPath . 'thumb/';
        // What's the original filename
        if($file){
            
            $filename = $file->getClientOriginalName();
            // Upload the original
            $original = $file->move($destinationPath, $filename);
            // Create a thumb sized from the original and save to the thumb path
            foreach ($this->profile_img_size_array as $key => $val) {
                $thumb = Image::make($original->getRealPath())
                            ->resize($key, $val)
                            ->save($destinationPathThumb . $filename); 
            } 
        }  
        
        $array = array(
            "status" => true,
            "message" => " image uploaded",
        );
        return $array;
           
    }

    public function demo_image_lib(Request $request) {
                       
        $file = $request->file('image');

        // Define our destinations        
        $destinationPath = substr(base_path(), 0, strrpos(base_path(), '/')) . '/uploads/';
        $destinationPathThumb = $destinationPath . 'thumb/';

        // What's the original filename
        if($file){            
                
            $filename = $file->getClientOriginalName();
            
            // Upload the original
            $original = $file->move($destinationPath, $filename);           
            
            // Create a thumb sized from the original and save to the thumb path
            foreach ($this->profile_img_size_array as $key => $val) {
                $real_path = $original->getRealPath();                
                list($width_orig, $height_orig, $image_type) = getimagesize($real_path);				                                                
                                                            
                if ($width_orig != $key || $height_orig != $val) {  
                    $oc_image = new OC_Image;                                                                                                                                                                  
                    $oc_image::initialize($real_path);                                                       
                    $oc_image::resize($key, $val);
                    $oc_image::save($destinationPathThumb . $key . "x" . $val . "_" . $filename);
                } else {
                    copy($real_path, $destinationPathThumb . $key . "x" . $val . "_" . $filename);
                }                 
            } 
        }        
        
        $array = array(
            "status" => true,
            "message" => " image uploaded",
        );
        
        return $array;
    }   

    public function GetRefrralNo($promo_code) {
        $data = DB::table('member')
                ->where('user_name', trim($promo_code))
                ->first();
        if ($data) {
            return $data->member_id;
        }
    }

    function generate_otp($len) {
        $r_str = "";
        $chars = "0123456789";
        do {
            $r_str = "";
            for ($i = 0; $i < $len; $i++) {
                $r_str .= substr($chars, rand(0, strlen($chars)), 1);
            }
        } while (strlen($r_str) != $len);
        return $r_str;
    }

    function generateUsername($user_name) {
        $chars = "0123456789";
        $r_str = '';
        for ($i = 0; $i < 6; $i++) {
            $r_str .= substr($chars, rand(0, strlen($chars)), 1);
        }
        $new_user_name = $user_name . $r_str;
        $data = DB::table('member')
                ->where('user_name', $new_user_name)
                ->first();
        if ($data) {
            $this->generateUsername($user_name);
        } else {
            return $new_user_name;
        }
    }

    public function checkMobileNumber(Request $request) {
        $array = array();
        $validator = Validator::make($request->all(), [
                    'mobile_no' => 'required|unique:member|numeric|digits_between:7,15',], [
                    'mobile_no.required' => trans('message.err_mobile_no_req'),
                    'mobile_no.numeric' => trans('message.err_mobile_no_num'),
                    'mobile_no.unique' => trans('message.err_mobile_no_exist'),
                    'mobile_no.digits_between' => trans('message.err_mobile_no_7to15'),
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        } else {
            $referral_id = 0;
            if ($request->input('promo_code') && $request->input('promo_code') != '') {
                $referral_id = $this->GetRefrralNo($request->input('promo_code'));
                if (!$referral_id) {
                    $array['status'] = 'false';
                    $array['title'] = 'Error!';
                    $array['message'] = trans('message.err_referral_code_valid');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            $array['status'] = 'true';
            $array['title'] = 'Success!';
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function checkMember(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'register') {
            $array = array();
            $validator = Validator::make($request->all(), [
                        'promo_code' => 'exists:member,user_name',
                        'mobile_no' => 'required|unique:member|numeric|digits_between:7,15',
                        'user_name' => 'required|unique:member',
//                        'country_id' => 'required',
                        'country_code' => 'required',
                        'email_id' => [
                            'required',
                            Rule::unique('member')->where(function ($query) {
                                        return $query->where('login_via', '0');
                                    }),
                        ],
                        'password' => 'required|min:6',
                        'cpassword' => 'required|same:password|min:6',
                            ], [
                        'promo_code.exists' => trans('message.err_referral_code_valid'),
                        'user_name.required' => trans('message.err_username_req'),
                        'user_name.unique' => trans('message.err_username_exist'),
                        'mobile_no.required' => trans('message.err_mobile_no_req'),
                        'mobile_no.numeric' => trans('message.err_mobile_no_num'),
                        'mobile_no.unique' => trans('message.err_mobile_no_exist'),
                        'mobile_no.digits_between' => trans('message.err_mobile_no_7to15'),
                        'email_id.required' => trans('message.err_email_req'),
                        'email_id.email' => trans('message.err_email_valid'),
                        'email_id.unique' => trans('message.err_email_exist'),
                        'password.required' => trans('message.err_password_req'),
                        'password.min' => trans('message.err_password_min'),
                        'cpassword.required' => trans('message.err_cpassword_req'),
                        'cpassword.same' => trans('message.err_pass_cpass_not_same'),
                        'cpassword.min' => trans('message.err_cpassword_min'),
            ]);
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            } else {
                $array['status'] = 'true';
                $array['title'] = 'Success!';
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    public function createMember_fb(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'fb_login') {
            $validator = Validator::make($request->all(), [
                        'fb_id' => 'required',
                    ]);
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;                            
            }
            $user = DB::table('member')->where('fb_id', $request->input('fb_id'))->where('login_via', '1')->get();
            if ($user->count() <= 0) {
                $api_token = uniqid() . base64_encode(str_random(40));
                $user_name = $this->generateUsername($request->input('user_name'));
                $email_id = '';
                if ($request->input('email_id') != NULL)
                    $email_id = $request->input('email_id');
                $member_data = [
                    'user_name' => $user_name,
                    'mobile_no' => '',
                    'email_id' => $email_id,
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'player_id' => $request->input('player_id'),
                    'password' => md5($request->input('fb_id')),
                    'fb_id' => $request->input('fb_id'),
                    'login_via' => '1',
                    'api_token' => $api_token,
                    'entry_from' => '1',
                    'new_user' => 'Yes',
                    'created_date' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')];
                $member_id = DB::table('member')->insertGetId($member_data);
                $member_data['member_id'] = $member_id;
                $array['status'] = 'true';
                $array['title'] = trans('message.text_succ_register');
                $array['message'] = (object) $member_data;
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                if ($user[0]->member_status == '1') {
                    
                    $player_id_data = [
                            'player_id' => $request->input('player_id')];
                    
                    DB::table('member')->where('member_id', $user[0]->member_id)->update($player_id_data);
                    
                    $user_data = DB::table('member')->where('member_id', $user[0]->member_id)->get();
                    
                    $array['status'] = 'true';
                    $array['title'] = trans('message.text_succ_login');
                    $array['message'] = $user_data[0];
                    $array['member_id'] = $user_data[0]->member_id;
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $array['status'] = 'false';
                    $array['title'] = trans('message.text_fail_login');
                    $array['message'] = trans('message.text_block_acc');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }
    }

    public function createMember_google(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'google_login') {
            $validator = Validator::make($request->all(), [
                        'email_id' => 'required',
                        'g_id' => 'required',
            ]);
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;                            
            }
            $user = DB::table('member')->where('fb_id', $request->input('g_id'))->where('login_via', '2')->get();
            if ($user->count() <= 0) {
                $api_token = uniqid() . base64_encode(str_random(40));
                $user_name = $this->generateUsername($request->input('user_name'));
                $member_data = [
                    'user_name' => $user_name,
                    'mobile_no' => '',
                    'email_id' => $request->input('email_id'),
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'player_id' => $request->input('player_id'),
                    'password' => md5($request->input('g_id')),
                    'fb_id' => $request->input('g_id'),
                    'login_via' => '2',
                    'api_token' => $api_token,
                    'entry_from' => '1',
                    'new_user' => 'Yes',
                    'created_date' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')];
                $member_id = DB::table('member')->insertGetId($member_data);
                $member_data['member_id'] = $member_id;
                $array['status'] = 'true';
                $array['title'] = trans('message.text_succ_register');
                $array['message'] = (object) $member_data;
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                if ($user[0]->member_status == '1') {
                    
                    $player_id_data = [
                            'player_id' => $request->input('player_id')];
                    
                    DB::table('member')->where('member_id', $user[0]->member_id)->update($player_id_data);
                    
                    $user_data = DB::table('member')->where('member_id', $user[0]->member_id)->get();
                    
                    $array['status'] = 'true';
                    $array['title'] = trans('message.text_succ_login');
                    $array['message'] = $user_data[0];
                    $array['member_id'] = $user_data[0]->member_id;
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $array['status'] = 'false';
                    $array['title'] = trans('message.text_fail_login');
                    $array['message'] = trans('message.text_block_acc');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }
    }

    function GetCountryId($country_code) {
        $country = DB::table('country')->where('p_code', $country_code)->select('country_id')->first();
        if ($country)
            return $country->country_id;
        else
            return 0;
    }

    public function UpdateMobileNo(Request $request) {
        $validator = Validator::make($request->all(), ['member_id' => 'required', 'country_code' => 'required', 'mobile_no' => 'required|unique:member|numeric|digits_between:7,15',
                        ], ['member_id.required' => trans('message.err_member_id'),
                    'country_code.required' => trans('message.err_country_code_req'),
                    'mobile_no.required' => trans('message.err_mobile_no_req'),
                    'mobile_no.numeric' => trans('message.err_mobile_no_num'),
                    'mobile_no.unique' => trans('message.err_mobile_no_exist'),
                    'mobile_no.digits_between' => trans('message.err_mobile_no_7to15')]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        } else {
//            $country_id = $this->GetCountryId($request->input('country_code'));
            $member_data = array(
                'mobile_no' => $request->input('mobile_no'),
//                'country_id' => $country_id, 
                'country_code' => $request->input('country_code'),
                'new_user' => 'No');

            $referral_id = 0;
            if ($request->input('promo_code') != '') {
                $referral_id = $this->GetRefrralNo($request->input('promo_code'));
                $member_data['referral_id'] = $referral_id;
            }
            DB::table('member')->where('member_id', $request->input('member_id'))->update($member_data);
            $array['status'] = 'true';
            $array['title'] = 'Success!';
            $array['message'] = trans('message.text_succ_mobile_no_ins');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function createMember(Request $request)
    {
        if ($request->input('submit') == 'register') {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required',
                'last_name' => 'required',
                'promo_code' => 'exists:member,user_name',
                'mobile_no' => 'required|unique:member|numeric|digits_between:7,10',
                'user_name' => 'required|unique:member',
                'country_code' => 'required',
                'email_id' => [
                    'required',
                    Rule::unique('member')->where(fn($query) => $query->where('login_via', '0')),
                ],
                'password' => 'required|min:6',
                'cpassword' => 'required|same:password|min:6',
            ], [
                'first_name.required' => trans('message.err_fname_req'),
                'last_name.required' => trans('message.err_lname_req'),
                'promo_code.exists' => trans('message.err_referral_code_valid'),
                'user_name.required' => trans('message.err_username_req'),
                'user_name.unique' => trans('message.err_username_exist'),
                'mobile_no.required' => trans('message.err_mobile_no_req'),
                'mobile_no.numeric' => trans('message.err_mobile_no_num'),
                'mobile_no.unique' => trans('message.err_mobile_no_exist'),
                'mobile_no.digits_between' => trans('message.err_mobile_no_7to15'),
                'email_id.required' => trans('message.err_email_req'),
                'email_id.unique' => trans('message.err_email_exist'),
                'password.required' => trans('message.err_password_req'),
                'cpassword.same' => trans('message.err_pass_cpass_not_same'),
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'title' => 'Error!', 'message' => $validator->errors()->first()]);
            }

            $referral_id = 0;
            if ($request->input('promo_code') != '') {
                $referral_id = $this->GetRefrralNo($request->input('promo_code'));
            }

            $api_token = uniqid() . base64_encode(str_random(40));
            $member_data = [
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'player_id' => $request->input('player_id'),
                'user_name' => $request->input('user_name'),
                'email_id' => $request->input('email_id'),
                'mobile_no' => $request->input('mobile_no'),
                'password' => md5($request->input('password')),
                'country_code' => $request->input('country_code'),
                'referral_id' => $referral_id,
                'api_token' => $api_token,
                'entry_from' => '1',
                'referral_bonus_given' => 0,
                'mob_verify' => 0,
                'mob_verify_otp' => null,
                'created_date' => Carbon::now($this->timezone)->format('Y-m-d H:i:s')
            ];

            $member_id = DB::table('member')->insertGetId($member_data);

            return response()->json([
                'status' => true,
                'title' => trans('message.text_succ_register'),
                'message' => trans('message.text_succ_register_login'),
                'member_id' => $member_id,
                // 'api_token' => $api_token,
                'code' => 'mob_not_verify',             // ← Added
                'mobNo' => $request->input('mobile_no') // ← Added
            ]);
        }
    }


    public function getAnnouncement() {
        $data['announcement'] = DB::table('announcement')
                ->get();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getSlider() {
        $data['slider'] = DB::table('slider')
                ->where('slider.status', '1')
                ->leftJoin('game as g', 'g.game_id', '=', 'slider.link_id')
                ->select('slider.*', DB::raw('(CASE 
                        WHEN slider_image = "" THEN ""
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/slider_image/thumb/1000x500_", slider_image) 
                        END) AS slider_image'), 'g.game_name')
                ->orderBy('slider_id', 'ASC')
                ->get();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getBanner() {
        $data['banner'] = DB::table('banner')
                ->where('banner.status', '1')
                ->leftJoin('game as g', 'g.game_id', '=', 'banner.link_id')
                ->select('banner.*', DB::raw('(CASE 
                        WHEN banner_image = "" THEN "" 
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/banner_image/thumb/1000x500_", banner_image) 
                        END) AS banner_image'), 'g.game_name')
                ->orderBy('banner_id', 'ASC')
                ->get();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getWatchAndEarn($member_id) {
        $data['watch_earn']['watch_ads_per_day'] = $this->system_config['watch_ads_per_day'];
        $data['watch_earn']['point_on_watch_ads'] = $this->system_config['point_on_watch_ads'];
        $data['watch_earn']['watch_earn_description'] = $this->system_config['watch_earn_description'];
        $data['watch_earn']['watch_earn_note'] = $this->system_config['watch_earn_note'];

        $total_watch_ads = DB::table('watch_earn')
                ->where("member_id", $member_id)
                ->where("watch_earn_date", date('Y-m-d'))
                ->select("rewards")
                ->first();
        if ($total_watch_ads)
            $data['watch_earn']['total_watch_ads'] = $total_watch_ads->rewards;
        else
            $data['watch_earn']['total_watch_ads'] = 0;
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getWatchAndEarn2($member_id) {
        $total_watch_ads = DB::table('watch_earn')
                ->where("member_id", $member_id)
                ->where("watch_earn_date", date('Y-m-d'))
                ->select("rewards", "watch_earn_id")
                ->first();

        if ($total_watch_ads) {
            $rewards = $total_watch_ads->rewards + 1;

            if ($rewards >= $this->system_config['watch_ads_per_day']) {
                $member = DB::table('member')
                        ->where("member_id", $member_id)
                        ->select("join_money", "wallet_balance")
                        ->first();
                $wallet_balance = $member->wallet_balance + $this->system_config['point_on_watch_ads'];
                $join_money = $member->join_money;
                $browser = '';
                $agent = new Agent();
                if ($agent->isMobile()) {
                    $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                } elseif ($agent->isDesktop()) {
                    $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                } elseif ($agent->isRobot()) {
                    $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                }
                $ip = $this->getIp();
                $acc_data = [
                    'member_id' => $member_id,
                    'deposit' => $this->system_config['point_on_watch_ads'],
                    'withdraw' => 0,
                    'join_money' => $join_money,
                    'win_money' => $wallet_balance,
                    'note' => 'Watch And Earn',
                    'note_id' => '13',
                    'entry_from' => '1',
                    'ip_detail' => $ip,
                    'browser' => $browser,
                    'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                ];
                DB::table('accountstatement')->insert($acc_data);

                $upd_data = [
                    'wallet_balance' => $wallet_balance];
                DB::table('member')->where('member_id', $member_id)->update($upd_data);

                $upd_watch_earn_data = [
                    'rewards' => $rewards,
                    'earning' => $this->system_config['point_on_watch_ads'],];
                DB::table('watch_earn')->where('watch_earn_id', $total_watch_ads->watch_earn_id)->update($upd_watch_earn_data);

                $array['status'] = 'true';
                $array['title'] = 'Success!';
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $upd_watch_earn_data = [
                    'rewards' => $rewards,];
                DB::table('watch_earn')->where('watch_earn_id', $total_watch_ads->watch_earn_id)->update($upd_watch_earn_data);
                $array['status'] = 'true';
                $array['title'] = 'Success!';
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $watch_earn_data = [
                'member_id' => $member_id,
                'rewards' => 1,
                'earning' => 0,
                'watch_earn_date' => date('Y-m-d'),
                'date_created' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            DB::table('watch_earn')->insert($watch_earn_data);
            $array['status'] = 'true';
            $array['title'] = 'Success!';
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function getWatchAndEarnDetail($member_id) {
        $total = DB::table('watch_earn')
                ->where("member_id", $member_id)
                ->select(DB::raw("SUM(rewards) as total_rewards"), DB::raw("SUM(earning) as total_earning"))
                ->first();
        $data['total_rewards'] = $total->total_rewards;
        $data['total_earning'] = $total->total_earning;
        $data['watch_earn_data'] = DB::table('watch_earn')->orderBy('watch_earn_date', 'DESC')
                        ->where("member_id", $member_id)->get();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getIp() {
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    return $ip;
                }
            }
        }
    }

    public function getAllCountry() {
        $data['all_country'] = DB::table('country')
                ->select('*')
                ->where("country_status", '1')
                ->orderBy('country_id', 'ASC')
                ->get();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getAllLanguage() {
        $data['supported_language'] = json_decode($this->system_config['supported_language']);
        $data['rtl_supported_language'] = json_decode($this->system_config['rtl_supported_language']);
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getAllGame() {				
		
		$member_id = Auth::user()->member_id;
		
        $data['all_game'] = DB::table('game as g')
                ->select('*', \DB::raw('(select count(*) from matches as m where m.game_id = g.game_id and m.match_status = "1") as total_upcoming_match'),\DB::raw('(select count(*) from ludo_challenge as l where l.game_id = g.game_id and l.accept_status = "0" and l.challenge_status = "1" and l.member_id != "'. $member_id .'" and l.accepted_member_id != "'. $member_id .'") as total_upcoming_challenge'), \DB::raw('(CASE 
                        WHEN g.game_image = "" THEN "" 
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/game_image/thumb/1000x500_", g.game_image) 
                        END) AS game_image'), \DB::raw('(CASE 
                        WHEN g.game_logo = "" THEN "" 
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/game_logo_image/thumb/100x100_", g.game_logo) 
                        END) AS game_logo'))
                ->where("status", '1')
                
                ->orderBy('game_id', 'ASC')
                ->get();
                                     		
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getAllLottery($member_id, $status) {
        $member_id = Auth::user()->member_id;
        $query = DB::table('lottery')
                ->leftJoin('image as i', 'i.image_id', '=', 'lottery.image_id')
                ->select('lottery.*', \DB::raw('(CASE 
                        WHEN lottery.image_id != 0 THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/select_image/thumb/1000x500_", i.image_name)
                        WHEN lottery.lottery_image != "" THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/lottery_image/thumb/1000x500_", lottery.lottery_image) 
                        ELSE ""
                        END) AS lottery_image'))
                ->orderBy('lottery_id', 'ASC');
        if ($status == 'ongoing') {
            $array_name = 'ongoing';
            $query = $query->where("lottery_status", '1');
        } else if ($status == 'result') {
            $array_name = 'result';
            $query = $query->where("lottery_status", '2');
        }
        $data[$array_name] = $query->get();
        $lottery_id = array();
        foreach ($data[$array_name] as $row) {
            $lottery_id[] = $row->lottery_id;
        }
        $lottery_join = DB::table('lottery_member')
                ->where("member_id", $member_id)
                ->whereIn("lottery_id", $lottery_id)
                ->get();
        $i = 0;
        foreach ($data[$array_name] as $row) {
            $data[$array_name][$i]->member_id = "";
            $data[$array_name][$i]->join_status = "false";
            foreach ($lottery_join as $lottery_join_id) {
                if ($row->lottery_id == $lottery_join_id->lottery_id) {
                    $data[$array_name][$i]->join_status = "true";
                    $data[$array_name][$i]->member_id = $member_id;
                }
            }
            $data[$array_name][$i]->won_by = '';

            $data[$array_name][$i]->join_member = DB::table('lottery_member as l')
                    ->leftJoin('member as m', 'm.member_id', '=', 'l.member_id')
                    ->where("lottery_id", $row->lottery_id)
                    ->select('l.*', 'm.user_name')
                    ->get();
            foreach ($data[$array_name][$i]->join_member as $lottery_join_member) {
                if ($lottery_join_member->status == '1') {
                    $data[$array_name][$i]->won_by = $lottery_join_member->user_name;
                }
            }
            $i++;
        }


        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function singleLottery($lottery_id, $member_id) {
        $member_id = Auth::user()->member_id;
        $data['lottery'] = DB::table('lottery as l')
                ->leftJoin('image as i', 'i.image_id', '=', 'l.image_id')
                ->leftJoin(DB::raw("(select * from lottery_member where member_id='$member_id') as lm"), 'lm.lottery_id', '=', 'l.lottery_id')
                ->select('l.*', 'lm.member_id', \DB::raw('(CASE 
                        WHEN l.lottery_image = "" THEN "" 
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/lottery_image/thumb/1000x500_", l.lottery_image) 
                        END) AS lottery_image'), \DB::raw('(CASE 
                        WHEN l.image_id != 0 THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/select_image/thumb/1000x500_", i.image_name)                          
                        END) AS lottery_image'))
                ->where("l.lottery_id", $lottery_id)
                ->first();
        if ($data['lottery']->member_id == $member_id) {
            $data['lottery']->join_status = "true";
        } else {
            $data['lottery']->join_status = "false";
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getAllOngoingMatch($game_id, $member_id) {
        $member_id = Auth::user()->member_id;
        DB::statement("SET sql_mode = '' ");
        $matches = DB::table('matches')
                ->leftJoin('image as i', 'i.image_id', '=', 'matches.image_id')
                ->where("match_status", '3')
                ->where("game_id", $game_id)
                ->select('m_id', 'match_name', 'match_url', 'matches.room_description', 'match_time', 
                        'matches.win_prize', 'prize_description', 'per_kill', 'entry_fee', 'type', 
                        'MAP', 'match_type', 'match_desc', 'match_private_desc', 'no_of_player', 
                        'number_of_position', \DB::raw('(CASE 
                        WHEN matches.image_id != 0 THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/select_image/thumb/1000x500_", i.image_name)
                        WHEN matches.match_banner != "" THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/match_banner_image/thumb/1000x500_", matches.match_banner) 
                        ELSE ""
                        END) AS match_banner'), 'match_sponsor')
                ->groupBy('m_id')
                ->orderBy('match_time', 'ASC')
                ->get();

        // Convert collection to array and handle nulls
        $data['all_ongoing_match'] = [];
        foreach ($matches as $match) {
            $matchArray = (array)$match;
            $processedMatch = [];
            
            // Handle each field - if not null keep original value, else empty string
            foreach ($matchArray as $key => $value) {
                $processedMatch[$key] = $value !== null ? $value : "";
            }
            
            $data['all_ongoing_match'][] = (object)$processedMatch;
        }

        $match_id = array();
        foreach ($data['all_ongoing_match'] as $row) {
            $match_id[] = $row->m_id;
        }

        $match_joins = DB::table('match_join_member')
                ->where("member_id", $member_id)
                ->whereIn("match_id", $match_id)
                ->get();

        // Convert match joins to array and handle nulls
        $match_joins_array = [];
        foreach ($match_joins as $join) {
            $joinArray = (array)$join;
            $processedJoin = [];
            
            foreach ($joinArray as $key => $value) {
                $processedJoin[$key] = $value !== null ? $value : "";
            }
            
            $match_joins_array[] = (object)$processedJoin;
        }

        $i = 0;
        foreach ($data['all_ongoing_match'] as $row) {
            $room_description = $row->room_description !== null ? $row->room_description : "";
            
            // Initialize match data with empty strings
            $matchData = [
                'member_id' => "",
                'join_status' => "false",
                'room_description' => $room_description,
                'm_id' => $row->m_id,
                'match_name' => $row->match_name !== null ? $row->match_name : "",
                'match_url' => $row->match_url !== null ? $row->match_url : "",
                'match_time' => $row->match_time !== null ? $row->match_time : "",
                'win_prize' => $row->win_prize !== null ? $row->win_prize : "",
                'prize_description' => $row->prize_description !== null ? $row->prize_description : "",
                'per_kill' => $row->per_kill !== null ? $row->per_kill : "",
                'entry_fee' => $row->entry_fee !== null ? $row->entry_fee : "",
                'type' => $row->type !== null ? $row->type : "",
                'MAP' => $row->MAP !== null ? $row->MAP : "",
                'match_type' => $row->match_type !== null ? $row->match_type : "",
                'match_desc' => $row->match_desc !== null ? $row->match_desc : "",
                'match_private_desc' => $row->match_private_desc !== null ? $row->match_private_desc : "",
                'no_of_player' => $row->no_of_player !== null ? $row->no_of_player : "",
                'number_of_position' => $row->number_of_position !== null ? $row->number_of_position : "",
                'match_banner' => $row->match_banner !== null ? $row->match_banner : "",
                'match_sponsor' => $row->match_sponsor !== null ? $row->match_sponsor : ""
            ];

            $data['all_ongoing_match'][$i] = (object)$matchData;

            foreach ($match_joins_array as $match_join_id) {
                if ($row->m_id == $match_join_id->match_id) {
                    $data['all_ongoing_match'][$i]->join_status = "true";
                    $data['all_ongoing_match'][$i]->member_id = $member_id;
                }
            }
            $i++;
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getAllGameResult($game_id, $member_id) {
        $member_id = Auth::user()->member_id;
        DB::statement("SET sql_mode = '' ");
        $matches = DB::table('matches as m')
                ->leftJoin('image as i', 'i.image_id', '=', 'm.image_id')
                ->where("match_status", '2')
                ->where("game_id", $game_id)
                ->select('m_id', 'match_name', 'match_url', 'm.room_description', 
                        DB::raw("STR_TO_DATE(match_time, '%d/%m/%Y %h:%i %p') as m_time"), 
                        'match_time', 'm.win_prize', 'prize_description', 'per_kill', 
                        'entry_fee', 'type', 'MAP', 'match_type', 'match_desc',
                        'match_private_desc', 'no_of_player', 'number_of_position', 
                        \DB::raw('(CASE 
                        WHEN m.image_id != 0 THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/select_image/thumb/1000x500_", i.image_name)
                        WHEN m.match_banner != "" THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/match_banner_image/thumb/1000x500_", m.match_banner) 
                        ELSE ""
                        END) AS match_banner'), 'match_sponsor')
                ->groupBy('m_id')
                ->orderBy('m_time', 'DESC')
                ->limit(10)
                ->get();

        // Convert collection to array and handle nulls
        $data['all_game_result'] = [];
        foreach ($matches as $match) {
            $matchArray = (array)$match;
            $processedMatch = [];
            
            // Handle each field - if not null keep original value, else empty string
            foreach ($matchArray as $key => $value) {
                $processedMatch[$key] = $value !== null ? $value : "";
            }
            
            $data['all_game_result'][] = (object)$processedMatch;
        }

        $match_id = array();
        foreach ($data['all_game_result'] as $row) {
            $match_id[] = $row->m_id;
        }

        $match_joins = DB::table('match_join_member')
                ->where("member_id", $member_id)
                ->whereIn("match_id", $match_id)
                ->get();

        // Convert match joins to array and handle nulls
        $match_joins_array = [];
        foreach ($match_joins as $join) {
            $joinArray = (array)$join;
            $processedJoin = [];
            
            foreach ($joinArray as $key => $value) {
                $processedJoin[$key] = $value !== null ? $value : "";
            }
            
            $match_joins_array[] = (object)$processedJoin;
        }

        $i = 0;
        foreach ($data['all_game_result'] as $row) {
            $room_description = $row->room_description !== null ? $row->room_description : "";
            
            // Initialize match data with empty strings
            $matchData = [
                'member_id' => "",
                'join_status' => "false",
                'room_description' => $room_description,
                'm_id' => $row->m_id,
                'match_name' => $row->match_name !== null ? $row->match_name : "",
                'match_url' => $row->match_url !== null ? $row->match_url : "",
                'm_time' => $row->m_time !== null ? $row->m_time : "",
                'match_time' => $row->match_time !== null ? $row->match_time : "",
                'win_prize' => $row->win_prize !== null ? $row->win_prize : "",
                'prize_description' => $row->prize_description !== null ? $row->prize_description : "",
                'per_kill' => $row->per_kill !== null ? $row->per_kill : "",
                'entry_fee' => $row->entry_fee !== null ? $row->entry_fee : "",
                'type' => $row->type !== null ? $row->type : "",
                'MAP' => $row->MAP !== null ? $row->MAP : "",
                'match_type' => $row->match_type !== null ? $row->match_type : "",
                'match_desc' => $row->match_desc !== null ? $row->match_desc : "",
                'match_private_desc' => $row->match_private_desc !== null ? $row->match_private_desc : "",
                'no_of_player' => $row->no_of_player !== null ? $row->no_of_player : "",
                'number_of_position' => $row->number_of_position !== null ? $row->number_of_position : "",
                'match_banner' => $row->match_banner !== null ? $row->match_banner : "",
                'match_sponsor' => $row->match_sponsor !== null ? $row->match_sponsor : ""
            ];

            $data['all_game_result'][$i] = (object)$matchData;

            foreach ($match_joins_array as $match_join_id) {
                if ($row->m_id == $match_join_id->match_id) {
                    $data['all_game_result'][$i]->join_status = "true";
                    $data['all_game_result'][$i]->member_id = $member_id;
                }
            }
            $i++;
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getAllPlayMatch($game_id, $member_id) {
        $member_id = Auth::user()->member_id;
        DB::statement("SET sql_mode = '' ");
        $data['allplay_match'] = DB::table('matches as m')
                ->leftJoin('image as i', 'i.image_id', '=', 'm.image_id')
                ->select('pin_match', 'm_id', 'match_name', 'match_url', 'm.room_description', DB::raw("STR_TO_DATE(match_time, '%d/%m/%Y %h:%i %p') as m_time"), 'match_time', 'm.win_prize', 'prize_description', 'per_kill', 'entry_fee', 'type', 'MAP', 'match_type', 'match_desc','match_private_desc', 'no_of_player', 'number_of_position', \DB::raw('(CASE 
                        WHEN m.image_id != 0 THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/select_image/thumb/1000x500_", i.image_name) 
                        WHEN m.match_banner != "" THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/match_banner_image/thumb/1000x500_", m.match_banner) 
                        ELSE ""
                        END) AS match_banner'), 'match_sponsor')
                ->where("match_status", '1')
                ->where("game_id", $game_id)
                ->groupBy('m_id')
                ->orderBy('pin_match', 'DESC')
                ->orderBy('m_time', 'ASC')
                ->get();
        $match_id = array();
        foreach ($data['allplay_match'] as $row) {
            $match_id[] = $row->m_id ?? '';
        }
        $match_join = DB::table('match_join_member')
                ->where("member_id", $member_id)
                ->whereIn("match_id", $match_id)
                ->groupBy('match_id')
                ->get();
        $i = 0;
        foreach ($data['allplay_match'] as $row) {
            $room_description = $data['allplay_match'][$i]->room_description;            
            $data['allplay_match'][$i]->room_description = "";            
            $data['allplay_match'][$i]->member_id = "";
            $data['allplay_match'][$i]->join_status = "false";
            foreach ($match_join as $match_join_id) {
                if ($row->m_id == $match_join_id->match_id) {
                    $data['allplay_match'][$i]->join_status = "true";
                    $data['allplay_match'][$i]->member_id = $member_id;
                    if ($room_description != '')
                        $data['allplay_match'][$i]->room_description = $room_description;                    
                }
            }
            $i++;
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getMyMatches($member_id) {
        $member_id = Auth::user()->member_id;
        DB::statement("SET sql_mode = '' ");
        $data['my_match'] = DB::table('match_join_member as mj')
                ->leftJoin('matches as m', 'mj.match_id', '=', 'm.m_id')
                ->leftJoin('image as i', 'i.image_id', '=', 'm.image_id')
                ->leftJoin('game as g', 'g.game_id', '=', 'm.game_id')
                ->select('g.game_name', 'm_id', 'match_name', 'match_url', 'm.room_description', DB::raw("STR_TO_DATE(match_time, '%d/%m/%Y %h:%i %p') as m_time"), 'match_time', 'm.win_prize', 'prize_description', 'per_kill', 'entry_fee', 'type', 'MAP', 'match_type', 'match_desc','match_private_desc', 'no_of_player', 'number_of_position', \DB::raw('(CASE 
                        WHEN m.image_id != 0 THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/select_image/thumb/1000x500_", i.image_name) 
                        WHEN m.match_banner != "" THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/match_banner_image/thumb/1000x500_", m.match_banner) 
                        ELSE ""
                        END) AS match_banner'), 'match_sponsor', 'match_status', 'mj.member_id')
                ->where("match_status", '!=', '0')
                ->where("match_status", '!=', '4')
                ->where("mj.member_id", $member_id)
                ->groupBy('m_id')
                ->orderBy('m_time', 'ASC')
                ->get();
        $i = 0;
        foreach ($data['my_match'] as $my_match) {
            $data['my_match'][$i]->join_status = "true";
            if ($data['my_match'][$i]->room_description != '')
                $data['my_match'][$i]->room_description = $data['my_match'][$i]->room_description;            
            $i++;
        }
        echo json_encode($data,JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getDashboardDetails($member_id) {
        $member_id = Auth::user()->member_id;
        $member_data = DB::table('member')
                ->where("member_id", $member_id)
                ->select('password', 'first_name', 'last_name', 'user_name', 'wallet_balance', 'join_money', 
        'pubg_id', 'member_status', 'profile_image', 'mobile_no', 'email_id')
                ->first();

        // Format profile image URL and convert nulls to empty strings  
        $data['member'] = array_map(function($value) {
            return $value === null ? "" : $value;
        }, (array)$member_data);
        
        // Convert wallet_balance and join_money to numbers
        $data['member']['wallet_balance'] = strval($data['member']['wallet_balance']);
        $data['member']['join_money'] = strval($data['member']['join_money']);

        
        if (!empty($data['member']['profile_image'])) {
            $data['member']['profile_image'] = $this->base_url . '/uploads/profile_image/thumb/100x100_' . $data['member']['profile_image'];
        }

        $total = DB::table('match_join_member')
                ->where("member_id", $member_id)
                ->select(DB::raw("COUNT(match_join_member_id) as total_match"), DB::raw("SUM(COALESCE(killed, 0)) as total_kill"), DB::raw("SUM(COALESCE(total_win, 0)) as total_win"))
                ->first();

        // Convert match stats nulls to empty strings
        $data['tot_match_play']['total_match'] = $total->total_match ?? "0";
        $data['tot_kill']['total_kill'] = $total->total_kill ?? "0";
        $data['tot_win']['total_win'] = $total->total_win ?? "0";

        $withdraw = DB::table('accountstatement')
                ->where("member_id", $member_id)
                ->where("note_id", '8')                
                ->select(DB::raw("SUM(withdraw) as tot_withdraw"))
                ->first();

        // Convert withdraw null to empty string
        $data['tot_withdraw']['tot_withdraw'] = $withdraw->tot_withdraw ?? "";

        // Web config values (these should already be non-null from system_config)
        $data['web_config']['share_description'] = $this->system_config['share_description'] ?? "";
        $data['web_config']['referandearn_description'] = $this->system_config['referandearn_description'] ?? "";
        $data['web_config']['active_referral'] = $this->system_config['active_referral'] ?? "";

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function aboutUs() {
        $data['about_us'] = DB::table('page')
                ->where("page_slug", 'about-us')
                ->select('page_content as aboutus')
                ->first();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function customerSupport() {
        $data['customer_support']['company_street'] = $this->system_config['company_street'];
        $data['customer_support']['company_address'] = $this->system_config['company_address'];
        $data['customer_support']['comapny_phone'] = $this->system_config['comapny_phone'];
        $data['customer_support']['comapny_country_code'] = $this->system_config['comapny_country_code'];
        $data['customer_support']['company_time'] = $this->system_config['company_time'];
        $data['customer_support']['company_email'] = $this->system_config['company_email'];
        $data['customer_support']['insta_link'] = $this->system_config['insta_link'];
        if ($this->system_config['insta_link'] == '' || $this->system_config['insta_link'] == '#')
            $data['customer_support']['insta_link'] = '';
        else
            $data['customer_support']['insta_link'] = substr(rtrim($this->system_config['insta_link'], '/'), strrpos(rtrim($this->system_config['insta_link'], '/'), '/') + 1);

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function leadeBoard() {
        DB::statement("SET sql_mode = '' ");
        $data['leader_board'] = DB::table('member as m')
                ->leftJoin('member as m2', 'm.referral_id', '=', 'm2.member_id')
                ->select('m2.user_name', 'm.referral_id', DB::raw("COUNT(m.referral_id) as tot_referral"))
                ->whereNotIn('m.referral_id', array(0))
                ->groupBy('m.referral_id')
                ->orderBy('tot_referral', 'DESC')
                ->limit(10)
                ->get();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function matchParticipate($match_id) {
        $data['match_participate'] = DB::table('match_join_member as mj')
                ->leftJoin('member as m', 'mj.member_id', '=', 'm.member_id')
                ->where("match_id", $match_id)
                ->select('mj.pubg_id')
                ->get();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function myProfile($member_id) {
        $member_id = Auth::user()->member_id;
        $data['my_profile'] = DB::table('member')
                ->where("member_id", $member_id)
                ->select("password", "member_id", "first_name", "last_name", "user_name", "email_id", "country_id", "country_code", "mobile_no", "join_money", "wallet_balance", "pubg_id", "dob", "gender", DB::raw('(CASE 
                WHEN profile_image = "" THEN ""
                ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", profile_image) 
                END) AS profile_image'))
                ->first();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function myRefrrrals($member_id) {
        $member_id = Auth::user()->member_id;
        $data['tot_referrals'] = DB::table('member')
                ->where("referral_id", $member_id)
                ->select(DB::raw("count(member_id) as total_ref"))
                ->first();

        $data['tot_earnings'] = DB::table('referral')
                ->where("member_id", $member_id)
                ->where("referral_status", '0')
                ->select(DB::raw("sum(referral_amount) as total_earning"))
                ->first();

        $data['my_referrals'] = DB::table('member')
                ->where("referral_id", $member_id)
                ->select(DB::raw("date(created_date) as date"), "user_name", "member_status", "member_package_upgraded")
                ->get();
        $i = 0;
        foreach ($data['my_referrals'] as $row) {
            if ($row->member_status == '1' && $row->member_package_upgraded == '1') {
                $data['my_referrals'][$i]->status = trans('message.text_rewarded');
            } else if ($row->member_status == '1') {
                $data['my_referrals'][$i]->status = trans('message.text_registered');
            } else {
                $data['my_referrals'][$i]->status = trans('message.text_inactive');
            }
            $i++;
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function myStatistics($member_id) {
        $member_id = Auth::user()->member_id;
        $data['my_statistics'] = DB::table('match_join_member as mj')
                ->Join('matches as m', 'm.m_id', '=', 'mj.match_id')
                ->where("member_id", $member_id)
                ->select('m.match_name', 'm.m_id', 'match_time', 'entry_fee as paid', 'total_win as won')
                ->get();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function singleGameResult($match_id) {
        $data['match_deatils'] = DB::table('matches')
                ->where("m_id", $match_id)
                ->select('m_id', 'match_name', 'match_time', 'win_prize', 'per_kill', 'entry_fee', 'match_url', 'type', 'match_type', 'result_notification', 'match_sponsor')
                ->first();

        if ($data['match_deatils']->type == 'Solo') {
            $limit = 1;
        } elseif ($data['match_deatils']->type == 'Duo') {
            $limit = 2;
        } elseif ($data['match_deatils']->type == 'Squad') {
            $limit = 4;
        } elseif ($data['match_deatils']->type == 'Squad5') {
            $limit = 5;
        }

        $data['full_result'] = DB::table('match_join_member as mj')
                        ->where("match_id", $match_id)
                        ->leftJoin('member as m', 'mj.member_id', '=', 'm.member_id')
                        ->select('user_name', 'mj.pubg_id', 'killed', 'total_win')
                        ->orderBy('win_prize', 'DESC')
                        ->orderBy('total_win', 'DESC')
                        ->get()->toArray();
        $data['match_winner'] = array_slice($data['full_result'], 0, $limit);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public function reportsingleGameResult($match_id) {
    $data['match_deatils'] = DB::table('matches')
        ->where("m_id", $match_id)
        ->select('m_id', 'match_name', 'match_time', 'win_prize', 'per_kill', 'entry_fee', 'match_url', 'type', 'match_type', 'result_notification', 'match_sponsor')
        ->first();

    if ($data['match_deatils']->type == 'Solo') {
        $limit = 1;
    } elseif ($data['match_deatils']->type == 'Duo') {
        $limit = 2;
    } elseif ($data['match_deatils']->type == 'Squad') {
        $limit = 4;
    } elseif ($data['match_deatils']->type == 'Squad5') {
        $limit = 5;
    }

    $data['full_result'] = DB::table('match_join_member as mj')
        ->where("match_id", $match_id)
        ->leftJoin('member as m', 'mj.member_id', '=', 'm.member_id')
        ->select('mj.member_id', 'user_name', 'mj.pubg_id', 'killed', 'total_win') // added mj.member_id
        ->orderBy('win_prize', 'DESC')
        ->orderBy('total_win', 'DESC')
        ->get()
        ->toArray();

    $data['match_winner'] = array_slice($data['full_result'], 0, $limit);

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
    }
    
    
    public function sendroomnoti($match_id) {
    $data['match_deatils'] = DB::table('matches')
        ->where("m_id", $match_id)
        ->select('m_id', 'match_name', 'match_time', 'match_url', 'type', 'match_type')
        ->first();

    $all_players = DB::table('match_join_member as mj')
        ->where("match_id", $match_id)
        ->leftJoin('member as m', 'mj.member_id', '=', 'm.member_id')
        ->select('mj.member_id', 'm.user_name', 'mj.pubg_id')
        ->get()
        ->toArray();

    $data['all_joined_player'] = $all_players;

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
    }


    public function singleMatch($match_id, $member_id) {
        $member_id = Auth::user()->member_id;
        $data['match'] = DB::table('matches as m')
                ->leftJoin(DB::raw("(select * from match_join_member where member_id='$member_id') as mj"), 'mj.match_id', '=', 'm.m_id')
                ->leftJoin('game as g', 'g.game_id', '=', 'm.game_id')
                ->where("m_id", $match_id)
                ->select('m_id', 'match_name', 'match_time', 'm.win_prize', 'per_kill', 'entry_fee', 'type', 'MAP', 'match_type', 'match_desc','match_private_desc', 'no_of_player', 'number_of_position', 'mj.member_id', 'match_url', 'm.roomid', 'm.room_pass', 'm.room_description', 'match_sponsor', 'g.package_name')
                ->orderBy('match_time', 'ASC')
                ->first();
        $data['join_position'] = DB::table('match_join_member')
                ->where("member_id", $member_id)
                ->where("match_id", $match_id)
                ->select('pubg_id', 'team', 'position', 'match_join_member_id')
                ->get();


        if ($data['match']->member_id == $member_id) {
            $data['match']->join_status = "true";
            // Show all room details to the match creator
            if ($data['match']->roomid == '') $data['match']->roomid = "";
            if ($data['match']->room_pass == '') $data['match']->room_pass = "";
            if ($data['match']->room_description == '') $data['match']->room_description = "";
        } else {
            // Hide room details from other users
            $data['match']->roomid = "";
            $data['match']->room_pass = "";
            $data['match']->room_description = "";
            $data['match']->join_status = "false";
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function termsConditions() {
        $data['terms_conditions'] = DB::table('page')
                ->where("page_slug", 'terms_conditions')
                ->select('page_content as terms_conditions')
                ->first();

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function topPlayers() {
        DB::statement("SET sql_mode = '' ");
        $game = DB::table('game')
                ->select('*', \DB::raw('(CASE 
                        WHEN game_logo = "" THEN "" 
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/game_logo_image/thumb/100x100_", game_logo) 
                        END) AS game_logo'))
                ->where("status", '1')
                ->get();
        $data['game'] = array();
        $data['top_players'] = array();
        foreach ($game as $row) {
            $data['top_players'][$row->game_name] = DB::table('match_join_member as mj')
                    ->join('member as m', function ($join) {
                        $join->on('m.member_id', '=', 'mj.member_id');
                    })
                    ->join('matches as m1', function ($join) {
                        $join->on('m1.m_id', '=', 'mj.match_id');
                    })
                    ->where("m1.game_id", $row->game_id)
                    ->select(
                        DB::raw("sum(total_win) as winning"),
                        DB::raw("SUM(COALESCE(killed, 0)) as total_kill"),
                        DB::raw("COUNT(match_join_member_id) as total_match"),
                        'm.user_name',
                        'm.member_id',
                        'm.pubg_id',
                        'm.profile_image'
                    )
                    ->groupBy('mj.member_id')
                    ->orderBy('winning', 'DESC')
                    ->take(10)
                    ->get();

            // Format each player's data
            foreach ($data['top_players'][$row->game_name] as &$player) {
                // Convert numeric values to integers
                $player->winning = intval($player->winning ?? 0);
                $player->total_kill = intval($player->total_kill ?? 0);
                $player->total_match = intval($player->total_match ?? 0);
                
                // Handle serialized pubg_id
                if (!empty($player->pubg_id)) {
                    $unserialized = @unserialize($player->pubg_id);
                    if ($unserialized !== false) {
                        $player->pubg_id = is_array($unserialized) ? reset($unserialized) : "";
                    }
                }

                // Format profile image URL
                if (!empty($player->profile_image)) {
                    $player->profile_image = $this->base_url . '/uploads/profile_image/thumb/100x100_' . $player->profile_image;
                } else {
                    $player->profile_image = "";
                }
            }

            if ($data['top_players'][$row->game_name]->count() > 0) {
                $data['game'][] = $row;
            }
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getAllProduct() {
        $query = DB::table('product')
                ->select('*', \DB::raw('(CASE 
                        WHEN product_image != "" THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/product_image/thumb/1000x500_", product_image) 
                        ELSE ""
                        END) AS product_image'))
                ->where("product_status", '1')
                ->orderBy('product_id', 'DESC');
        $data['product'] = $query->get();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function singleProduct($product_id) {
        $data['product'] = DB::table('product')
                ->select('*', \DB::raw('(CASE 
                        WHEN product_image != "" THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/product_image/thumb/1000x500_", product_image) 
                        ELSE ""
                        END) AS product_image'))
                ->where("product_id", $product_id)
                ->first();
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function MyOrder($member_id) {
        $data['my_orders'] = DB::table('orders as o')
                ->where("member_id", $member_id)
                ->leftjoin('courier as c', 'o.courier_id', '=', 'c.courier_id')
                ->select('o.*', DB::raw('(CASE 
                        WHEN c.courier_link != "" THEN CONCAT (c.courier_link,o.tracking_id) 
                        ELSE ""
                        END) AS courier_link'), DB::raw('(CASE 
                        WHEN product_image != "" THEN CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/product_image/thumb/1000x500_", product_image) 
                        ELSE ""
                        END) AS product_image'), DB::raw('DATE_FORMAT(created_date, "%M %d %Y") as created_date'))
                ->orderBy('orders_id', 'DESC')
                ->get();
        $i = 0;
        foreach ($data['my_orders'] as $row) {
            $shipping_address = @unserialize($row->shipping_address);
            $data['my_orders'][$i]->name = $shipping_address['name'];
            $data['my_orders'][$i]->address = $shipping_address['address'];
            $data['my_orders'][$i]->add_info = '';
            if (isset($shipping_address['add_info']))
                $data['my_orders'][$i]->add_info = $shipping_address['add_info'];
            $i++;
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    public function AllOrders() {
        $data['all_orders'] = DB::table('orders as o')
            ->leftJoin('courier as c', 'o.courier_id', '=', 'c.courier_id')
            ->select(
                'o.*',
                DB::raw('(CASE 
                    WHEN c.courier_link != "" THEN CONCAT(c.courier_link, o.tracking_id) 
                    ELSE "" 
                END) AS courier_link'),
                DB::raw('(CASE 
                    WHEN product_image != "" THEN CONCAT("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/product_image/thumb/1000x500_", product_image) 
                    ELSE "" 
                END) AS product_image'),
                DB::raw('DATE_FORMAT(o.created_date, "%M %d %Y") as created_date')
            )
            ->orderBy('o.orders_id', 'DESC')
            ->get();
    
        $i = 0;
        foreach ($data['all_orders'] as $row) {
            $shipping_address = @unserialize($row->shipping_address);
            $data['all_orders'][$i]->name = $shipping_address['name'] ?? '';
            $data['all_orders'][$i]->address = $shipping_address['address'] ?? '';
            $data['all_orders'][$i]->add_info = $shipping_address['add_info'] ?? '';
            $i++;
        }
    
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }


    // public function ProductOrder(Request $request) {
    //     if (($request->input('submit')) && $request->input('submit') == 'order') {
    //         $validator = Validator::make($request->all(), [
    //                     'product_id' => 'required',
    //                     'member_id' => 'required',
    //                     'shipping_address' => 'required',
    //                         ], [
    //                     'product_id.required' => trans('message.err_product_id'),
    //                     'member_id.required' => trans('message.err_member_id'),
    //                     'shipping_address' => trans('message.err_sho_address_req'),
    //         ]);
    //         if ($validator->fails()) {
    //             $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
    //         }
    //         $product = DB::table('product')
    //                 ->where('product_id', $request->input('product_id'))
    //                 ->first();
    //         if ($product->product_status != 1) {
    //             $array['status'] = 'false';
    //             $array['title'] = 'Error!';
    //             $array['message'] = 'Product not available';
    //             echo json_encode($array,JSON_UNESCAPED_UNICODE);
    //             exit;
    //         }
    //         $member = DB::table('member')
    //                 ->where('member_id', $request->input('member_id'))
    //                 ->first();
    //         if ($member->wallet_balance + $member->join_money >= $product->product_selling_price) {
    //             $invoice = DB::table('orders')
    //                     ->orderBy('orders_id', 'DESC')
    //                     ->limit(1)
    //                     ->first();
    //             if ($invoice) {
    //                 $invoice_no = $invoice->no + 1;
    //                 $no = $invoice->no + 1;
    //             } else {
    //                 $invoice_no = $no = 1;
    //             }
    //             $order_no = str_pad($invoice_no, 8, 'ORD0000', STR_PAD_LEFT);
    //             $order_data = [
    //                 'member_id' => $request->input('member_id'),
    //                 'no' => $no,
    //                 'order_no' => $order_no,
    //                 'product_name' => $product->product_name,
    //                 'product_image' => $product->product_image,
    //                 'product_price' => $product->product_selling_price,
    //                 'shipping_address' => serialize($request->input('shipping_address')),
    //                 'order_status' => trans('message.text_hold'),
    //                 'entry_from' => '1',
    //                 'created_date' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
    //             ];
    //             $order_id = DB::table('orders')->insertGetId($order_data);
    //             if ($member->join_money > $product->product_selling_price) {
    //                 $join_money = $member->join_money - $product->product_selling_price;
    //                 $wallet_balance = $member->wallet_balance;
    //             } elseif ($member->join_money < $product->product_selling_price) {
    //                 $join_money = 0;
    //                 $amount1 = $product->product_selling_price - $member->join_money;
    //                 $wallet_balance = $member->wallet_balance - $amount1;
    //             } elseif ($member->join_money == $product->product_selling_price) {
    //                 $join_money = 0;
    //                 $wallet_balance = $member->wallet_balance;
    //             }
    //             $data = [
    //                 'join_money' => $join_money,
    //                 'wallet_balance' => $wallet_balance,
    //             ];
    //             $browser = '';
    //             $agent = new Agent();
    //             if ($agent->isMobile()) {
    //                 $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
    //             } elseif ($agent->isDesktop()) {
    //                 $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
    //             } elseif ($agent->isRobot()) {
    //                 $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
    //             }
    //             $ip = $this->getIp();
    //             $acc_data = [
    //                 'member_id' => $request->input('member_id'),
    //                 'order_id' => $order_id,
    //                 'deposit' => 0,
    //                 'withdraw' => $product->product_selling_price,
    //                 'join_money' => $join_money,
    //                 'win_money' => $wallet_balance,
    //                 'note' => 'Product Order',
    //                 'note_id' => '12',
    //                 'entry_from' => '1',
    //                 'ip_detail' => $ip,
    //                 'browser' => $browser,
    //                 'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
    //             ];
    //             DB::table('accountstatement')->insert($acc_data);

    //             DB::table('member')->where('member_id', $request->input('member_id'))->update($data);

    //             $array['status'] = 'true';
    //             $array['title'] = 'Success!';
    //             $array['message'] = trans('message.text_succ_order');
    //             echo json_encode($array,JSON_UNESCAPED_UNICODE);
    //             exit;
    //         } else {
    //             $array['status'] = 'false';
    //             $array['title'] = 'Error!';
    //             $array['message'] = trans('message.err_balance_low');
    //             echo json_encode($array,JSON_UNESCAPED_UNICODE);
    //             exit;
    //         }
    //     }
    // }
    
    
    public function ProductOrder(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'order') {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required',
                'member_id' => 'required',
                'shipping_address' => 'required',
            ], [
                'product_id.required' => trans('message.err_product_id'),
                'member_id.required' => trans('message.err_member_id'),
                'shipping_address.required' => trans('message.err_sho_address_req'),
            ]);
    
            if ($validator->fails()) {
                $array = [
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => $validator->errors()->first()
                ];
                echo json_encode($array, JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            $product = DB::table('product')->where('product_id', $request->input('product_id'))->first();
            if (!$product || $product->product_status != 1) {
                $array = [
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => 'Product not available'
                ];
                echo json_encode($array, JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            $member = DB::table('member')->where('member_id', $request->input('member_id'))->first();
            if (!$member) {
                $array = [
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => 'Invalid member'
                ];
                echo json_encode($array, JSON_UNESCAPED_UNICODE);
                exit;
            }
    
            // ✅ Only check wallet_balance
            if ($member->wallet_balance >= $product->product_selling_price) {
    
                $invoice = DB::table('orders')->orderBy('orders_id', 'DESC')->first();
                $no = $invoice ? $invoice->no + 1 : 1;
                $order_no = str_pad($no, 8, 'ORD0000', STR_PAD_LEFT);
    
                $order_data = [
                    'member_id' => $request->input('member_id'),
                    'no' => $no,
                    'order_no' => $order_no,
                    'product_name' => $product->product_name,
                    'product_image' => $product->product_image,
                    'product_price' => $product->product_selling_price,
                    'shipping_address' => serialize($request->input('shipping_address')),
                    'order_status' => trans('message.text_hold'),
                    'entry_from' => '1',
                    'created_date' => Carbon::now($this->timezone)->format('Y-m-d H:i:s')
                ];
    
                $order_id = DB::table('orders')->insertGetId($order_data);
    
                // 💰 Deduct only from wallet_balance
                $wallet_balance = $member->wallet_balance - $product->product_selling_price;
    
                DB::table('member')->where('member_id', $request->input('member_id'))
                    ->update(['wallet_balance' => $wallet_balance]);
    
                $agent = new Agent();
                $browser = '';
                if ($agent->isMobile()) {
                    $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                } elseif ($agent->isDesktop()) {
                    $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                } elseif ($agent->isRobot()) {
                    $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                }
    
                $ip = $this->getIp();
                $acc_data = [
                    'member_id' => $request->input('member_id'),
                    'order_id' => $order_id,
                    'deposit' => 0,
                    'withdraw' => $product->product_selling_price,
                    'join_money' => $member->join_money, // unchanged
                    'win_money' => $wallet_balance,
                    'note' => 'Product Order (Wallet Balance)',
                    'note_id' => '12',
                    'entry_from' => '1',
                    'ip_detail' => $ip,
                    'browser' => $browser,
                    'accountstatement_dateCreated' => Carbon::now($this->timezone)->format('Y-m-d H:i:s')
                ];
                DB::table('accountstatement')->insert($acc_data);
    
                $array = [
                    'status' => 'true',
                    'title' => 'Success!',
                    'message' => trans('message.text_succ_order')
                ];
                echo json_encode($array, JSON_UNESCAPED_UNICODE);
                exit;
    
            } else {
                $array = [
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => trans('message.err_balance_low')
                ];
                echo json_encode($array, JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    public function joinLottery(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'joinnow') {
            $validator = Validator::make($request->all(), [
                        'lottery_id' => 'required',
                        'member_id' => 'required',
                            ], [
                        'lottery_id.required' => trans('message.err_lottery_id'),
                        'member_id.required' => trans('message.err_member_id'),
            ]);
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            }
            $lottery = DB::table('lottery')
                    ->where('lottery_id', $request->input('lottery_id'))
                    ->first();
            if ($lottery->lottery_size <= $lottery->total_joined) {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_no_spot');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
            $lottery_member = DB::table('lottery_member')
                    ->where('lottery_id', $request->input('lottery_id'))
                    ->where('member_id', $request->input('member_id'))
                    ->count();
            if ($lottery_member > 0) {                
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_already_join_lottery');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
            $member = DB::table('member')
                    ->where('member_id', $request->input('member_id'))
                    ->first();
            if ($member->wallet_balance + $member->join_money >= $lottery->lottery_fees) {
                $lottery_member = [
                    'lottery_id' => $request->input('lottery_id'),
                    'member_id' => $request->input('member_id'),
                    'entry_from' => '1',
                    'date_created' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                ];
                DB::table('lottery_member')->insert($lottery_member);
                if ($lottery->lottery_fees > 0) {
                    if ($member->join_money > $lottery->lottery_fees) {
                        $join_money = $member->join_money - $lottery->lottery_fees;
                        $wallet_balance = $member->wallet_balance;
                    } elseif ($member->join_money < $lottery->lottery_fees) {
                        $join_money = 0;
                        $amount1 = $lottery->lottery_fees - $member->join_money;
                        $wallet_balance = $member->wallet_balance - $amount1;
                    } elseif ($member->join_money == $lottery->lottery_fees) {
                        $join_money = 0;
                        $wallet_balance = $member->wallet_balance;
                    }
                    $data = [
                        'join_money' => $join_money,
                        'wallet_balance' => $wallet_balance,
                    ];
                    $browser = '';
                    $agent = new Agent();
                    if ($agent->isMobile()) {
                        $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                    } elseif ($agent->isDesktop()) {
                        $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                    } elseif ($agent->isRobot()) {
                        $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                    }
                    $ip = $this->getIp();
                    $acc_data = [
                        'member_id' => $request->input('member_id'),
                        'lottery_id' => $request->input('lottery_id'),
                        'deposit' => 0,
                        'withdraw' => $lottery->lottery_fees,
                        'join_money' => $join_money,
                        'win_money' => $wallet_balance,
                        'note' => 'Lottery Joined',
                        'note_id' => '10',
                        'entry_from' => '1',
                        'ip_detail' => $ip,
                        'browser' => $browser,
                        'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                    ];
                    DB::table('accountstatement')->insert($acc_data);

                    DB::table('member')->where('member_id', $request->input('member_id'))->update($data);

                    $total_joined = DB::table('lottery_member')
                                    ->where('lottery_id', $request->input('lottery_id'))
                                    ->select(DB::raw("COUNT(*) as total_joined"))
                                    ->first()->total_joined;
                    $data = [
                        'total_joined' => $total_joined];
                    DB::table('lottery')->where('lottery_id', $request->input('lottery_id'))->update($data);
                } else {
                    $total_joined = DB::table('lottery_member')
                                    ->where('lottery_id', $request->input('lottery_id'))
                                    ->select(DB::raw("COUNT(*) as total_joined"))
                                    ->first()->total_joined;
                    $data = [
                        'total_joined' => $total_joined];
                    DB::table('lottery')->where('lottery_id', $request->input('lottery_id'))->update($data);
                }
                $array['status'] = 'true';
                $array['title'] = 'Success!';
                $array['message'] = trans('message.text_succ_join');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_balance_low');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    public function joinMatchSingle($match_id) {
        $data = array();
        $match = DB::table('matches')
                ->where('m_id', $match_id)
                ->first();
        $member = DB::table('member')
                ->where('member_id', Auth::user()->member_id)
                ->first();
        
        if($member->pubg_id != ''){
            $pubg_id = unserialize($member->pubg_id);
        } else {
            $pubg_id = $member->pubg_id;
        }

        $data['pubg_id'] = '';
        if (is_array($pubg_id) && array_key_exists($match->game_id, $pubg_id)) {
        // if ($pubg_id->getType() && $pubg_id->getType()->getName() === 'array' && array_key_exists($match->game_id, $pubg_id)) {
            $data['pubg_id'] = $pubg_id[$match->game_id];
        }
        if ($match->no_of_player >= $match->number_of_position) {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.err_no_spot');
            echo json_encode($array, JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $match_join_member = DB::table('match_join_member as mj')
                    ->leftjoin('member as m', 'm.member_id', '=', 'mj.member_id')
                    ->leftjoin('matches as ma', 'ma.m_id', '=', 'mj.match_id')
                    ->where('mj.match_id', $match_id)
                    ->select('m.user_name', 'mj.pubg_id', 'mj.team', 'mj.position', 'ma.type')
                    ->orderBy('team', 'ASC')
                    ->orderBy('position', 'ASC')
                    ->get();

            if ($match->type == 'Solo') {
                for ($j = 1; $j <= $match->number_of_position; $j++) {
                    if (count($match_join_member) == 0) {
                        $a = array(
                            'user_name' => '',
                            'pubg_id' => '',
                            'team' => '1',
                            'position' => $j,
                        );
                    } else {
                        foreach ($match_join_member as $res) {
                            if ($res->position == $j) {
                                $a = array(
                                    'user_name' => $res->user_name,
                                    'pubg_id' => $res->pubg_id,
                                    'team' => '1',
                                    'position' => $j,
                                );
                                break;
                            } else {
                                $a = array(
                                    'user_name' => '',
                                    'pubg_id' => '',
                                    'team' => '1',
                                    'position' => $j,
                                );
                            }
                        }
                    }
                    $data['result'][] = $a;
                }
            } elseif ($match->type == 'Duo') {
                $loop = ceil($match->number_of_position / 2);
                for ($j = 1; $j <= $loop; $j++) {
                    for ($i = 1; $i <= 2; $i++) {
                        if (count($match_join_member) == 0) {
                            $a = array(
                                'user_name' => '',
                                'pubg_id' => '',
                                'team' => $j,
                                'position' => $i,
                            );
                        } else {
                            foreach ($match_join_member as $res) {
                                if ($res->team == $j && $res->position == $i) {
                                    $a = array(
                                        'user_name' => $res->user_name,
                                        'pubg_id' => $res->pubg_id,
                                        'team' => $res->team,
                                        'position' => $res->position,
                                    );
                                    break;
                                } else {
                                    $a = array(
                                        'user_name' => '',
                                        'pubg_id' => '',
                                        'team' => $j,
                                        'position' => $i,
                                    );
                                }
                            }
                        }
                        $data['result'][] = $a;
                    }
                }
            } elseif ($match->type == 'Squad') {
                $loop = ceil($match->number_of_position / 4);
                for ($j = 1; $j <= $loop; $j++) {
                    for ($i = 1; $i <= 4; $i++) {
                        if (count($match_join_member) == 0) {
                            $a = array(
                                'user_name' => '',
                                'pubg_id' => '',
                                'team' => $j,
                                'position' => $i,
                            );
                        } else {
                            foreach ($match_join_member as $res) {
                                if ($res->team == $j && $res->position == $i) {
                                    $a = array(
                                        'user_name' => $res->user_name,
                                        'pubg_id' => $res->pubg_id,
                                        'team' => $res->team,
                                        'position' => $res->position,
                                    );
                                    break;
                                } else {
                                    $a = array(
                                        'user_name' => '',
                                        'pubg_id' => '',
                                        'team' => $j,
                                        'position' => $i,
                                    );
                                }
                            }
                        }
                        $data['result'][] = $a;
                    }
                }
            } elseif ($match->type == 'Squad5') {
                $loop = ceil($match->number_of_position / 5);
                for ($j = 1; $j <= $loop; $j++) {
                    for ($i = 1; $i <= 5; $i++) {
                        if (count($match_join_member) == 0) {
                            $a = array(
                                'user_name' => '',
                                'pubg_id' => '',
                                'team' => $j,
                                'position' => $i,
                            );
                        } else {
                            foreach ($match_join_member as $res) {
                                if ($res->team == $j && $res->position == $i) {
                                    $a = array(
                                        'user_name' => $res->user_name,
                                        'pubg_id' => $res->pubg_id,
                                        'team' => $res->team,
                                        'position' => $res->position,
                                    );
                                    break;
                                } else {
                                    $a = array(
                                        'user_name' => '',
                                        'pubg_id' => '',
                                        'team' => $j,
                                        'position' => $i,
                                    );
                                }
                            }
                        }
                        $data['result'][] = $a;
                    }
                }
            }
            $data['match'] = $match;
            $array['status'] = 'true';
            $array['title'] = 'Success!';
            $array['message'] = $data;

            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function updateMyprofile(Request $request) {
        $data = $request->json()->all();
        
        if (!empty($data['submit']) && $data['submit'] == 'save') {
            $user = DB::table('member')->where('member_id', $data['member_id'])->first();
            
            // Validation based on login_via
            if ($user->login_via == '0') {
                $member_id = Auth::user()->member_id;
                $validator = Validator::make($data, [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'country_code' => 'required',
                    'user_name' => 'required|unique:member,user_name,' . $data['member_id'] . ',member_id',
                    'email_id' => [
                        'required',
                        Rule::unique('member')->where(function ($query) {
                            return $query->where('member_id', '!=', Auth::user()->member_id)
                                       ->where('login_via', '0');
                        }),
                    ],
                    'mobile_no' => 'required|unique:member,mobile_no,' . $data['member_id'] . ',member_id|numeric|digits_between:7,15',
                ], [
                    'first_name.required' => trans('message.err_fname_req'),
                    'last_name.required' => trans('message.err_lname_req'),
                    'user_name.required' => trans('message.err_username_req'),
                    'user_name.unique' => trans('message.err_username_exist'),
                    'email_id.required' => trans('message.err_email_req'),
                    'email_id.unique' => trans('message.err_email_exist'),
                    'country_code.required' => trans('message.err_country_code_req'),
                    'mobile_no.required' => trans('message.err_mobile_no_req'),
                    'mobile_no.numeric' => trans('message.err_mobile_no_num'),
                    'mobile_no.unique' => trans('message.err_mobile_no_exist'),
                    'mobile_no.digits_between' => trans('message.err_mobile_no_7to15'),
                ]);
            } elseif ($user->login_via == '1') {
                $validator = Validator::make($data, [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'user_name' => 'required|unique:member,user_name,' . $data['member_id'] . ',member_id',
                    'mobile_no' => 'required|unique:member,mobile_no,' . $data['member_id'] . ',member_id|numeric|digits_between:7,15',
                ], [
                    'first_name.required' => trans('message.err_fname_req'),
                    'last_name.required' => trans('message.err_lname_req'),
                    'user_name.required' => trans('message.err_username_req'),
                    'user_name.unique' => trans('message.err_username_exist'),
                    'country_code.required' => trans('message.err_country_code_req'),
                    'mobile_no.required' => trans('message.err_mobile_no_req'),
                    'mobile_no.numeric' => trans('message.err_mobile_no_num'),
                    'mobile_no.digits_between' => trans('message.err_mobile_no_7to15'),
                ]);
            } else {
                $validator = Validator::make($data, [
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'user_name' => 'required|unique:member,user_name,' . $data['member_id'] . ',member_id',
                    'email_id' => ['required'],
                    'mobile_no' => 'required|unique:member,mobile_no,' . $data['member_id'] . ',member_id|numeric|digits_between:7,15',
                ], [
                    'first_name.required' => trans('message.err_fname_req'),
                    'last_name.required' => trans('message.err_lname_req'),
                    'user_name.required' => trans('message.err_username_req'),
                    'user_name.unique' => trans('message.err_username_exist'),
                    'email_id.required' => trans('message.err_email_req'),
                    'country_code.required' => trans('message.err_country_code_req'),
                    'mobile_no.required' => trans('message.err_mobile_no_req'),
                    'mobile_no.numeric' => trans('message.err_mobile_no_num'),
                    'mobile_no.digits_between' => trans('message.err_mobile_no_7to15'),
                ]);
            }
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => $validator->errors()->first()
                ]);
            }
    
            // Handle profile image if present
            if (!empty($data['profile_image'])) {
                try {
                    // Decode base64 image
                    $image_parts = explode(";base64,", $data['profile_image']);
                    $image_type_aux = explode("image/", $image_parts[0]);
                    $image_type = $image_type_aux[1];
                    $image_base64 = base64_decode($image_parts[1]);
    
                    // Validate image type
                    if (!in_array($image_type, ['jpeg', 'png', 'jpg'])) {
                        return response()->json([
                            'status' => 'false',
                            'title' => 'Error!',
                            'message' => 'File type not valid!'
                        ]);
                    }
    
                    // Check file size (2MB limit)
                    if (strlen($image_base64) > 2000000) {
                        return response()->json([
                            'status' => 'false',
                            'title' => 'Error!',
                            'message' => 'Image size exceeds 2MB!'
                        ]);
                    }
    
                    $filename = 'member_' . rand() . '_' . $data['member_id'] . '.' . $image_type;
                    $destinationPath = substr(base_path(), 0, strrpos(base_path(), '/')) . '/uploads/profile_image/';
                    $destinationPathThumb = $destinationPath . 'thumb/';
    
                    // Remove old image if exists
                    if ($user->profile_image) {
                        if (file_exists($destinationPath . $user->profile_image)) {
                            @unlink($destinationPath . $user->profile_image);
                        }
                        foreach ($this->profile_img_size_array as $key => $val) {
                            $oldFile = $destinationPathThumb . $key . 'x' . $val . '_' . $user->profile_image;
                            if (file_exists($oldFile)) {
                                @unlink($oldFile);
                            }
                        }
                    }
    
                    // Save new image
                    file_put_contents($destinationPath . $filename, $image_base64);
    
                    // Create thumbnails
                    foreach ($this->profile_img_size_array as $key => $val) {
                        $oc_image = new OC_Image;
                        $oc_image::initialize($destinationPath . $filename);
                        $oc_image::resize($key, $val);
                        $oc_image::save($destinationPathThumb . $key . "x" . $val . "_" . $filename);
                    }
    
                    $updateData = [
                        'first_name' => $data['first_name'],
                        'last_name' => $data['last_name'],
                        'user_name' => $data['user_name'],
                        'email_id' => $data['email_id'],
                        'mobile_no' => $data['mobile_no'],
                        'country_code' => $data['country_code'],
                        'dob' => $data['dob'] ?? null,
                        'gender' => $data['gender'] ?? null,
                        'profile_image' => $filename
                    ];
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'false',
                        'title' => 'Error!',
                        'message' => 'Error processing image'
                    ]);
                }
            } else {
                $updateData = [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'user_name' => $data['user_name'],
                    'email_id' => $data['email_id'],
                    'mobile_no' => $data['mobile_no'],
                    'country_code' => $data['country_code'],
                    'dob' => $data['dob'] ?? null,
                    'gender' => $data['gender'] ?? null
                ];
            }
    
            $res = DB::table('member')->where('member_id', $data['member_id'])->update($updateData);
    
            return response()->json([
                'status' => 'true',
                'title' => 'Success!',
                'message' => 'Profile Updated Successfully'
            ]);
        }
    
        // Handle password reset
        if (!empty($data['submit']) && $data['submit'] == 'reset') {
            $validator = Validator::make($data, [
                'oldpass' => 'required',
                'newpass' => 'required',
                'confpass' => 'required|same:newpass|different:oldpass',
            ], [
                'oldpass.required' => trans('message.err_old_password_req'),
                'newpass.required' => trans('message.err_new_password_req'),
                'confpass.required' => trans('message.err_cpassword_req'),
                'confpass.same' => trans('message.err_pass_cpass_not_same'),
                'confpass.different' => trans('message.err_npassword_oldpass_same'),
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => $validator->errors()->first()
                ]);
            }
    
            if (md5($data['oldpass']) != Auth::user()->password) {
                return response()->json([
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => trans('message.err_old_pass_wrong')
                ]);
            }
    
            $res = DB::table('member')
                ->where('member_id', $data['member_id'])
                ->where('password', md5($data['oldpass']))
                ->update(['password' => md5($data['newpass'])]);
    
            return response()->json([
                'status' => 'true',
                'title' => 'Success!',
                'message' => trans('message.text_succ_pass_change')
            ]);
        }
    
        // Handle push notification settings
        if (!empty($data['submit']) && $data['submit'] == 'submit_push_noti') {
            $res = DB::table('member')
                ->where('member_id', $data['member_id'])
                ->update(['push_noti' => $data['push_noti']]);
    
            return response()->json([
                'status' => 'true',
                'title' => 'Success!',
                'message' => 'Push notification settings updated successfully'
            ]);
        }
    
        return response()->json([
            'status' => 'false',
            'title' => 'Error!',
            'message' => 'Invalid request'
        ]);
    }

    function generate_password($len) {
        $r_str = "";
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
        for ($i = 0; $i < $len; $i++)
            $r_str .= substr($chars, rand(0, strlen($chars)), 1);
        return $r_str;
    }

    public function sendOTP(Request $request) {
        if ($this->system_config['msg91_otp'] == '0' || $this->system_config['msg91_otp'] == 0) {
            $validator = Validator::make($request->all(), [
                        'email_mobile' => 'required|email',], [
                        'email_mobile.required' => trans('message.err_email_req'),
                        'email_mobile.email' => trans('message.err_email_valid'),
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                        'email_mobile' => 'required',
                            ], [
                        'email_mobile.required' => trans('message.err_email_or_mobile_req'),
            ]);
        }
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
        $member = DB::table('member as m')
                ->where('login_via', '0')
                ->where('email_id', $request->input('email_mobile'))
                ->orWhere('mobile_no', $request->input('email_mobile'))
                ->select('m.*')
                ->get();
        if ($member->count() > 0) {
            if (strtolower($member[0]->email_id) == strtolower($request->input('email_mobile'))) {
                $otp = $this->generate_OTP(6);
                $smtpUsername = $this->system_config['smtp_user'];
                $smtpPassword = urldecode($this->system_config['smtp_pass']);
                $emailFrom = $this->system_config['company_email'];
                $emailFromName = $this->system_config['company_name'];
                $emailTo = $request->input('email_mobile');
                $mail = new PHPMailer;

                $mail->isSMTP();
                $mail->Host = $this->system_config['smtp_host'];
                $mail->Port = $this->system_config['smtp_port'];
                $mail->SMTPSecure = $this->system_config['smtp_secure']; //'ssl'
                $mail->SMTPAuth = true;
                $mail->Username = $smtpUsername;
                $mail->Password = $smtpPassword;
                $mail->setFrom($smtpUsername, $emailFromName);
                $mail->addAddress($emailTo);
                $mail->isHTML(true);
                $mail->Subject = "Password Recover";
                $mail->Body = "<html>
                            <head>
                            <title>Password Recover </title>
                            </head>
                            <body>
                            <p>Your verification otp is : $otp</p>                            
                            </body>
                            </html>";
                $mail->send();
                $array['status'] = 'true';
                $array['title'] = 'Success!';
                $array['message'] = trans('message.text_succ_send_mail');
                $array['member_id'] = $member[0]->member_id;
                $array['otp'] = $otp;
                header('Access-Control-Allow-Origin: *');
                header('Content-type: application/json');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
//                $otp = $this->generate_OTP(6);
//                $to = $request->input('email_mobile');
//                $subject = "Password Recover";
//                $message = "<html>
//                            <head>
//                            <title>Password Recover </title>
//                            </head>
//                            <body>
//                            <p>Your verification otp is : $otp</p>                            
//                            </body>
//                            </html>";
//                $company_email = $this->system_config['company_email'];
//                $headers = "From: $company_email \r\n";
//                $headers .= "MIME-Version: 1.0\r\n";
//                $headers .= "Content-type: text/html\r\n";
//                if (mail($to, $subject, $message, $headers)) {
//                    $array['status'] = 'true';
//                    $array['title'] = 'Success!';
//                    $array['message'] = 'OTP send in mail.Please check your email.';
//                    $array['member_id'] = $member[0]->member_id;
//                    $array['otp'] = $otp;
//                    header('Access-Control-Allow-Origin: *');
//                    header('Content-type: application/json');
//                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
//                    exit;
//                } else {
//                    $array['status'] = 'false';
//                    $array['title'] = 'Error!';
//                    $array['message'] = 'mail not send !';
//                    header('Access-Control-Allow-Origin: *');
//                    header('Content-type: application/json');
//                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
//                    exit;
//                }
            } elseif ($member[0]->mobile_no == $request->input('email_mobile')) {
                $message = "Your verification code is : $otp";
                $m_number = $member[0]->country_code . $member[0]->mobile_no;
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => "http://api.msg91.com/api/sendhttp.php?sender=" . $this->system_config['msg91_sender'] . "&route=" . $this->system_config['msg91_route'] . "&mobiles=" . $m_number . "&authkey=" . $this->system_config['msg91_authkey'] . "&encrypt=0&country=" . $member[0]->country_code . "&message=" . urlencode($message) . "&response=json",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "GET",
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                ));
                $response = curl_exec($curl);
                $response = json_decode($response);
                $err = curl_error($curl);
                curl_close($curl);
                if ($response->type == 'success') {
                    $array['status'] = 'true';
                    $array['title'] = 'Success!';
                    $array['message'] = trans('message.text_succ_send_sms');
                    $array['member_id'] = $member[0]->member_id;
                    $array['otp'] = $otp;
                    header('Access-Control-Allow-Origin: *');
                    header('Content-type: application/json');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $array['status'] = 'false';
                    $array['title'] = 'Error!';
                    $array['message'] = trans('message.text_err_sms');
                    header('Access-Control-Allow-Origin: *');
                    header('Content-type: application/json');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        } else {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.err_email_or_mobile_exist');
            header('Access-Control-Allow-Origin: *');
            header('Content-type: application/json');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function forgotpassword(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'forgotpass') {
            $validator = Validator::make($request->all(), [
                        'member_id' => 'required',
                        'password' => 'required|min:6',
                        'cpassword' => 'required|same:password|min:6',
                            ], [
                        'member_id.required' => trans('message.err_member_id'),
                        'password.required' => trans('message.err_password_req'),
                        'password.min' => trans('message.err_password_min'),
                        'cpassword.required' => trans('message.err_cpassword_req'),
                        'cpassword.same' => trans('message.err_pass_cpass_not_same'),
                        'cpassword.min' => trans('message.err_cpassword_min'),
            ]);
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            }
            $res = DB::table('member')->where('member_id', $request->input('member_id'))->update(['password' => md5($request->input('password'))]);
            $array['status'] = 'true';
            $array['title'] = 'Success!';
            $array['message'] = trans('message.text_succ_pass_change');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function withdrawMethod() {
        $data['withdraw_method'] = DB::table('withdraw_method')
                ->leftJoin('currency as c', 'c.currency_id', '=', 'withdraw_method.withdraw_method_currency')
                ->where("withdraw_method_status", '1')
                ->select('withdraw_method.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')
                ->get();
        $data['min_withdrawal'] = $this->system_config['min_withdrawal'];
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function withdraw(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'withdraw') {
            $array = array();
            $currency = DB::table('currency')
                    ->where("currency_id", $this->system_config['currency'])
                    ->first();
            $withdraw_method = DB::table('withdraw_method')
                    ->leftJoin('currency as c', 'c.currency_id', '=', 'withdraw_method.withdraw_method_currency')
                    ->where("withdraw_method", $request->input('withdraw_method'))
                    ->select('withdraw_method_field')
                    ->first();
            if ($withdraw_method->withdraw_method_field == 'mobile no') {
                $validator = Validator::make($request->all(), [
                            'member_id' => 'required',
                            'pyatmnumber' => 'required|numeric|digits_between:7,15',
                            'amount' => 'required|numeric|min:' . $this->system_config['min_withdrawal'],
                                ], [
                            'member_id.required' => trans('message.err_member_id'),
                            'pyatmnumber.required' => trans('message.err_mobile_no_req'),
                            'pyatmnumber.numeric' => trans('message.err_mobile_no_num'),
                            'pyatmnumber.digits_between' => trans('message.err_mobile_no_7to15'),
                            'amount.required' => trans('message.err_amount_req'),
                            'amount.min' => trans('message.err_amount_min', ['currency' => '', 'amount' => $this->system_config['min_withdrawal']]),
                ]);
            } else if ($withdraw_method->withdraw_method_field == 'email') {
                $validator = Validator::make($request->all(), [
                            'member_id' => 'required',
                            'pyatmnumber' => 'required|email',
                            'amount' => 'required|numeric|min:' . $this->system_config['min_withdrawal'],
                                ], [
                            'member_id.required' => trans('message.err_member_id'),
                            'pyatmnumber.required' => trans('message.err_email_req'),
                            'pyatmnumber.email' => trans('message.err_email_valid'),
                            'amount.required' => trans('message.err_amount_req'),
                            'amount.min' => trans('message.err_amount_min', ['currency' => '', 'amount' => $this->system_config['min_withdrawal']]),
                ]);
            } else {
                $validator = Validator::make($request->all(), [
                            'member_id' => 'required',
                            'pyatmnumber' => 'required',
                            'amount' => 'required|numeric|min:' . $this->system_config['min_withdrawal'],
                                ], [
                            'member_id.required' => trans('message.err_member_id'),
                            'pyatmnumber.required' => trans('message.err_upi_req'),
                            'amount.required' => trans('message.err_amount_req'),
                            'amount.min' => trans('message.err_amount_min', ['currency' => '', 'amount' => $this->system_config['min_withdrawal']]),
                ]);
            }
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            }
            $member = DB::table('member')
                    ->where('member_id', $request->input('member_id'))
                    ->first();
            if ($member) {
                
                if($member->wallet_balance < $this->system_config['min_require_balance_for_withdrawal']) {
                    $array['status'] = 'false';
                    $array['title'] = 'Error!';
                    $array['message'] = 'Wallet Balance shoulde be greater than '. $this->system_config['min_require_balance_for_withdrawal'] .' for withdraw.';
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;                    
                }
    
                if ($member->wallet_balance >= $request->input('amount')) {
    
                    $wallet_balance = $member->wallet_balance - $request->input('amount');
                    $browser = '';
                    // Browser detection using native methods (replacing Agent package)
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $browser = $this->getBrowserInfo($user_agent);
                    
                    $ip = $this->getIp();
                    $acc_data = [
                        'member_id' => $request->input('member_id'),
                        'pubg_id' => $member->pubg_id,
                        'from_mem_id' => 0,
                        'deposit' => 0,
                        'withdraw' => $request->input('amount'),
                        'join_money' => $member->join_money,
                        'win_money' => $wallet_balance,
                        'pyatmnumber' => $request->input('pyatmnumber'),
                        'withdraw_method' => $request->input('withdraw_method'),
                        'note' => 'Withdraw Money from Win Wallet',
                        'note_id' => '9',
                        'entry_from' => '1',
                        'ip_detail' => $ip,
                        'browser' => $browser,
                        'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                    ];
                    $acc_id = DB::table('accountstatement')->insertGetId($acc_data);
                    $data = [
                        'wallet_balance' => $wallet_balance];
                    DB::table('member')->where('member_id', $request->input('member_id'))->update($data);
    
                    // ===== TELEGRAM NOTIFICATION ADDED HERE =====
                    $this->sendTelegramNotification(
                        $request->input('member_id'),
                        $request->input('amount'),
                        $request->input('withdraw_method'),
                        $request->input('pyatmnumber'),
                        $wallet_balance,
                        $ip
                    );
                    // ===== END TELEGRAM NOTIFICATION =====
                    
                    $array['status'] = 'true';
                    $array['title'] = 'Success!';
                    $array['message'] = trans('message.text_succ_withdraw', ['method' => $request->input('withdraw_method')]);
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $array['status'] = 'false';
                    $array['title'] = 'Error!';
                    $array['message'] = trans('message.err_balance_low');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_something_went_wrong');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
    
    /**
     * Send Telegram notification for withdrawal
     */
    private function sendTelegramNotification($memberId, $amount, $method, $account, $newBalance, $ip) {
        // Get credentials from .env
        $botToken = getenv('TELEGRAM_BOT_TOKEN');
        $chatId = getenv('TELEGRAM_CHAT_ID');
        
        // Skip if credentials are missing
        if (!$botToken || !$chatId) return;
        
        // Prepare message
        $message = "💸 *New Withdrawal Request* \n";
        $message .= "══════════════════════\n";
        $message .= "• *Member ID:* `$memberId`\n";
        $message .= "• *Amount:* ₹$amount\n";
        $message .= "• *Method:* $method\n";
        $message .= "• *Account:* `$account`\n";
        $message .= "• *New Balance:* ₹$newBalance\n";
        $message .= "• *IP Address:* `$ip`\n";
        $message .= "• *Time:* " . date('Y-m-d H:i:s') . "\n";
        
        // URL encode the message
        $encodedMessage = urlencode($message);
        
        // Create API URL
        $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=$encodedMessage&parse_mode=Markdown";
        
        // Send using file_get_contents (simplest method)
        @file_get_contents($url);
    }
    
    /**
     * Get browser information (replaces Agent package)
     */
    private function getBrowserInfo($user_agent) {
        $browser = "Unknown Browser";
        
        if (strpos($user_agent, 'MSIE') !== false) {
            $browser = 'Internet Explorer';
        } elseif (strpos($user_agent, 'Trident') !== false) { // For IE11
            $browser = 'Internet Explorer';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            $browser = 'Microsoft Edge';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $browser = 'Mozilla Firefox';
        } elseif (strpos($user_agent, 'Chrome') !== false) {
            $browser = 'Google Chrome';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $browser = 'Apple Safari';
        } elseif (strpos($user_agent, 'Opera') !== false) {
            $browser = 'Opera';
        }
        
        return $browser;
    }

    public function oneSignalApp() {
        $array = ["one_signal_app_id" => $this->system_config['app_id'], "one_signal_notification" => $this->system_config['one_signal_notification']];
        echo json_encode($array,JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function version($versionfor) {
        
        Cache::flush();
        header("Pragma: no-cache");
        header("Cache-Control: no-cache");
        header("Expires: 0");
        if ($versionfor == 'android') {
            $app_upload = DB::table('app_upload')
                    ->orderBy('app_upload_id', 'DESC')
                    ->first();
            $currency = DB::table('currency')
                    ->where("currency_id", $this->system_config['currency'])
                    ->first();
            $data['web_config']['currency'] = $currency->currency_code;
            $data['web_config']['currency_symbol'] = $currency->currency_symbol;
            if ($app_upload) {
                $array = ["currency_code" => $currency->currency_code, "currency_symbol" => $currency->currency_symbol, "banner_ads_show" => $this->system_config['banner_ads_show'], "fb_login" => $this->system_config['fb_login'], "google_login" => $this->system_config['google_login'], "firebase_otp" => $this->system_config['firebase_otp'], "version" => $app_upload->app_version, "force_update" => $app_upload->force_update, "force_logged_out" => $app_upload->force_logged_out, "url" => $this->base_url . '/' . $this->system_config['admin_photo'] . '/apk/' . $app_upload->app_upload, "description" => $app_upload->app_description];
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $array = ["currency_code" => $currency->currency_code, "currency_symbol" => $currency->currency_symbol, "banner_ads_show" => $this->system_config['banner_ads_show'], "fb_login" => $this->system_config['fb_login'], "google_login" => $this->system_config['google_login'], "firebase_otp" => $this->system_config['firebase_otp'], "version" => '1', "force_update" => "No", "force_logged_out" => "No", "url" => $this->base_url . '/' . $this->system_config['admin_photo'] . '/apk/', "description" => ''];
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
            }
        }
    }

    public function youTubeLink() {
        $data['youtube_links'] = DB::table('youtube_link')
                ->get();
        echo json_encode($data,JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function changePlayerName(Request $request) {
        $validator = Validator::make($request->all(), [
                    'match_id' => 'required',
                    'member_id' => 'required',
                    'pubg_id' => 'required',
                    'match_join_member_id' => 'required'
                        ], [
                    'match_id.required' => trans('message.err_match_id'),
                    'member_id.required' => trans('message.err_member_id'),
                    'pubg_id.required' => trans('message.err_playername_req'),
                    'match_join_member_id.required' => trans('message.err_match_join_member_id_id'),
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
        $match = DB::table('match_join_member')
                ->where('match_id', $request->input('match_id'))
                ->where('pubg_id', $request->input('pubg_id'))
                ->where('match_join_member_id', '!=', $request->input('match_join_member_id'))
                ->count();
        if ($match < 0) {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.err_playername_already_join', ['playername' => $request->input('pubg_id')]);
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
        $data = [
            "pubg_id" => $request->input('pubg_id')
        ];
        if (DB::table('match_join_member')->where('match_join_member_id', $request->input('match_join_member_id'))->update($data)) {
            $array['status'] = 'true';
            $array['title'] = 'Success!';
            $array['message'] = trans('message.text_succ_playername_change');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.text_err_playername_change');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function joinMatchProcess(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'joinnow') {

            $validator = Validator::make($request->all(), [
                        'match_id' => 'required',
                        'member_id' => 'required',
                        'teamposition' => 'required',
                            ], [
                        'match_id.required' => trans('message.err_match_id'),
                        'member_id.required' => trans('message.err_member_id'),
                        'teamposition.required' => trans('message.err_team_position'),
            ]);
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            }

            $resp = '';
            $m = DB::table('matches')
                    ->where('m_id', $request->input('match_id'))
                    ->first();
            if ($m->no_of_player + count($request->input('teamposition')) > $m->number_of_position) {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_no_spot');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
            foreach ($request->input('teamposition') as $teamposition) {
                $match = DB::table('match_join_member')
                        ->where('match_id', $request->input('match_id'))
                        ->where('pubg_id', $teamposition['pubg_id'])
                        ->get();
                if (count($match) > 0) {
                    $resp .= trans('message.err_playername_already_join', ['playername' => $teamposition['pubg_id']]);
                }
            }
            if ($resp != '') {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trim($resp, ', ');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
            $resp1 = '';
            foreach ($request->input('teamposition') as $teamposition) {
                $member = DB::table('match_join_member')
                        ->where('match_id', $request->input('match_id'))
                        ->where('team', $teamposition['team'])
                        ->where('position', $teamposition['position'])
                        ->get();
                if (count($member) > 0) {
                    $resp1 .= trans('message.err_playername_already_join', ['teamname' => $teamposition['team'], 'position' => $teamposition['position']]);
                }
            }
            if ($resp1 != '') {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trim($resp1, ', ');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
            $member_data = DB::table('member')
                    ->where('member_id', $request->input('member_id'))
                    ->first();
            $match_data = DB::table('matches')
                    ->where('m_id', $request->input('match_id'))
                    ->first();
            $ar_len = count($request->input('teamposition'));
            $fee = $match_data->entry_fee * $ar_len;
            if ($member_data->wallet_balance + $member_data->join_money >= $fee) {

                $ar_len = count($request->input('teamposition'));
                $i = 1;
                foreach ($request->input('teamposition') as $teamposition) {
                    $match_join_member_data = [
                        'match_id' => $request->input('match_id'),
                        'member_id' => $request->input('member_id'),
                        'pubg_id' => $teamposition['pubg_id'],
                        'team' => $teamposition['team'],
                        'position' => $teamposition['position'],
                        'place' => 0,
                        'place_point' => 0,
                        'killed' => 0,
                        'win' => 0,
                        'win_prize' => 0,
                        'bonus' => 0,
                        'total_win' => 0,
                        'refund' => 0,
                        'entry_from' => '1',
                        'date_craeted' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                    ];
                    DB::table('match_join_member')->insert($match_join_member_data);

                    if ($match_data->match_type == '0' || $match_data->match_type == 0) {
                        if ($i == 1 && $request->input('join_status') == "false") {

                            if($member_data->pubg_id != ''){
                                $pubg_id = unserialize($member_data->pubg_id);
                            } else {
                                $pubg_id = $member_data->pubg_id;
                            }
                            
                            if (is_array($pubg_id)) {
                                // if ($pubg_id->getType() && $pubg_id->getType()->getName() === 'array') {
                                    if (array_key_exists($match_data->game_id, $pubg_id)) {
                                    $pubg_id[$match_data->game_id] = $teamposition['pubg_id'];
                                } else {
                                    $pubg_id[$match_data->game_id] = $teamposition['pubg_id'];
                                }
                                $pubg_id = serialize($pubg_id);
                                $data = array(
                                    'pubg_id' => $pubg_id,
                                );
                            } else {
                                $pubg = array(
                                    $match_data->game_id => $teamposition['pubg_id'],
                                );
                                $pubg_id = serialize($pubg);
                                $data = array(
                                    'pubg_id' => $pubg_id,
                                );
                            }
                            DB::table('member')->where('member_id', $request->input('member_id'))->update($data);
                        }
                        if ($ar_len == $i) {
                            $no_of_player = DB::table('match_join_member')
                                            ->where('match_id', $request->input('match_id'))
                                            ->select(DB::raw("COUNT(*) as no_of_player"))
                                            ->first()->no_of_player;
                            $data = [
                                'no_of_player' => $no_of_player];
                            DB::table('matches')->where('m_id', $request->input('match_id'))->update($data);
                            $array['status'] = 'true';
                            $array['title'] = 'Success!';
                            $array['message'] = trans('message.text_succ_join');
                            echo json_encode($array,JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                    } else {
                        $row1 = DB::table('member')
                                ->where('member_id', $request->input('member_id'))
                                ->first();

                        if ($row1->join_money > $match_data->entry_fee) {
                            $join_money = $row1->join_money - $match_data->entry_fee;
                            $wallet_balance = $row1->wallet_balance;
                        } elseif ($row1->join_money < $match_data->entry_fee) {
                            $join_money = 0;
                            $amount1 = $match_data->entry_fee - $row1->join_money;
                            $wallet_balance = $row1->wallet_balance - $amount1;
                        } elseif ($row1->join_money == $match_data->entry_fee) {
                            $join_money = 0;
                            $wallet_balance = $row1->wallet_balance;
                        }
                        if ($i == 1 && $request->input('join_status') == "false") {

                            if($member_data->pubg_id != ''){
                                $pubg = unserialize($member_data->pubg_id);
                            } else {
                                $pubg = $member_data->pubg_id;
                            }

                            if(is_array($pubg)) {
                            // if ($pubg->getType() && $pubg->getType()->getName() === 'array') {
                                if (array_key_exists($match_data->game_id, $pubg)) {
                                    $pubg[$match_data->game_id] = $teamposition['pubg_id'];
                                } else {
                                    $pubg[$match_data->game_id] = $teamposition['pubg_id'];
                                }
                                $data = array(
                                    'pubg_id' => $pubg,
                                );
                            } else {

                                $pubg = array(
                                    $match_data->game_id => $teamposition['pubg_id'],
                                );
                                $pubg_id = serialize($pubg);
                                $data = array(
                                    'pubg_id' => $pubg_id,
                                );
                            }
                            $pubg_id = serialize($pubg);
                            $data = [
                                'join_money' => $join_money,
                                'wallet_balance' => $wallet_balance,
                                'pubg_id' => $pubg_id,
                            ];
                        } else {
                            $data = [
                                'join_money' => $join_money,
                                'wallet_balance' => $wallet_balance,
                            ];
                        }
                        $browser = '';
                        $agent = new Agent();
                        if ($agent->isMobile()) {
                            $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                        } elseif ($agent->isDesktop()) {
                            $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                        } elseif ($agent->isRobot()) {
                            $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                        }
                        $ip = $this->getIp();
                        $acc_data = [
                            'member_id' => $request->input('member_id'),
                            'pubg_id' => $teamposition['pubg_id'],
                            'match_id' => $request->input('match_id'),
                            'deposit' => 0,
                            'withdraw' => $match_data->entry_fee,
                            'join_money' => $join_money,
                            'win_money' => $wallet_balance,
                            'note' => 'Match Joined',
                            'note_id' => '2',
                            'entry_from' => '1',
                            'ip_detail' => $ip,
                            'browser' => $browser,
                            'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                        ];
                        DB::table('accountstatement')->insert($acc_data);

                        DB::table('member')->where('member_id', $request->input('member_id'))->update($data);

                        if ($row1->member_package_upgraded == 0 && (float)$match_data->entry_fee >= (float)$this->system_config['referral_min_paid_fee']) {                            
                            $wallet_balance = (float)$wallet_balance + (float)$this->system_config['referral']; //$SYSTEM_CONFIG['referral'];
                            
                            $data = [
                                'member_package_upgraded' => '1',
                            ];
                            DB::table('member')->where('member_id', $request->input('member_id'))->update($data);
                            if ($row1->referral_id != 0 && $this->system_config['active_referral'] == '1') {
                                $row2 = DB::table('member')
                                        ->where('member_id', $row1->referral_id)
                                        ->first();
                                if ($row2->member_package_upgraded == 1) {
                                    $join_money2 = $row2->join_money + $this->system_config['referral_level1']; //$SYSTEM_CONFIG['referral_level1'];
                                    $data = [
                                        'join_money' => $join_money2,];
                                    DB::table('member')->where('member_id', $row1->referral_id)->update($data);

                                    $referral_data = [
                                        'member_id' => $row2->member_id,
                                        'from_mem_id' => $request->input('member_id'),
                                        'referral_amount' => $this->system_config['referral_level1'], //$SYSTEM_CONFIG['referral']
                                        'referral_status' => '0',
                                        'entry_from' => '1',
                                         'referral_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                                    ];
                                    DB::table('referral')->insert($referral_data);
                                    $browser = '';
                                    $agent = new Agent();
                                    if ($agent->isMobile()) {
                                        $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                                    } elseif ($agent->isDesktop()) {
                                        $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                                    } elseif ($agent->isRobot()) {
                                        $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                                    }
                                    $ip = $this->getIp();
                                    $acc_data = [
                                        'member_id' => $row2->member_id,
                                        'pubg_id' => $row2->pubg_id,
                                        'from_mem_id' => $request->input('member_id'),
                                        'deposit' => $this->system_config['referral_level1'],
                                        'withdraw' => 0,
                                        'join_money' => $join_money2,
                                        'win_money' => $row2->wallet_balance,
                                        'note' => 'Referral',
                                        'note_id' => '4',
                                        'entry_from' => '1',
                                        'ip_detail' => $ip,
                                        'browser' => $browser,
                                         'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                                    ];
                                    DB::table('accountstatement')->insert($acc_data);
                                }
                            }
                        }
                        if ($ar_len == $i) {
                            $no_of_player = DB::table('match_join_member')
                                            ->where('match_id', $request->input('match_id'))
                                            ->select(DB::raw("COUNT(*) as no_of_player"))
                                            ->first()->no_of_player;
                            $data = [
                                'no_of_player' => $no_of_player];
                            DB::table('matches')->where('m_id', $request->input('match_id'))->update($data);
                            $array['status'] = 'true';
                            $array['title'] = 'Success!';
                            $array['message'] = trans('message.text_succ_join');
                            echo json_encode($array,JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                    }
                    $i++;
                }
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_balance_low');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }

    public function getPayment() {
        $data['payment'] = array();
        $payments = DB::table('pg_detail')
                        ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                        ->where('pg_detail.status', '1')->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')
                        ->orderBy('id', 'ASC')->get();
        $i = 0;
        foreach ($payments as $payment) {
            $data['payment'][$i]['payment_name'] = $payment->payment_name;
            $data['payment'][$i]['payment_status'] = $payment->payment_status;
            if ($payment->payment_name == 'PayTm') {
                $data['payment'][$i]['mid'] = $payment->mid;
                $data['payment'][$i]['mkey'] = $payment->mkey;
                $data['payment'][$i]['wname'] = $payment->wname;
                $data['payment'][$i]['ityp'] = $payment->itype;
            } else if ($payment->payment_name == 'PayPal') {
                $data['payment'][$i]['client_id'] = $payment->mid;
            } else if ($payment->payment_name == 'Offline') {
                $data['payment'][$i]['payment_description'] = $payment->payment_description;
            } else if ($payment->payment_name == 'PayStack') {
                $data['payment'][$i]['secret_key'] = $payment->mid;
                $data['payment'][$i]['public_key'] = $payment->mkey;
            } else if ($payment->payment_name == 'Instamojo') {
                $data['payment'][$i]['client_id'] = $payment->mid;
                $data['payment'][$i]['client_key'] = $payment->mkey;
            } else if ($payment->payment_name == 'Razorpay') {
                $data['payment'][$i]['api_secret'] = $payment->mkey;
                $data['payment'][$i]['key_id'] = $payment->mid;
            } else if ($payment->payment_name == 'Cashfree') {
                $data['payment'][$i]['secret_key'] = $payment->mkey;
                $data['payment'][$i]['app_id'] = $payment->mid;
            } else if ($payment->payment_name == 'Google Pay') {
                $data['payment'][$i]['upi_id'] = $payment->mid;
            } elseif ($payment->payment_name == 'PayU') {                
                $data['payment'][$i]['mkey'] = $payment->mkey;
                $data['payment'][$i]['salt'] = $payment->wname;               
            } 
            $data['payment'][$i]['currency_name'] = $payment->currency_name;
            $data['payment'][$i]['currency_point'] = $payment->currency_point;
            $data['payment'][$i]['currency_code'] = $payment->currency_code;
            $data['payment'][$i]['currency_symbol'] = $payment->currency_symbol;
            $i++;
        }

        $data['min_addmoney'] = $this->system_config['min_addmoney'];
        echo json_encode($data,JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function getPaymentDetails() {
        $row = DB::table('pg_detail')->where('id', $this->system_config['payment'])->first();
        $data['client_id'] = $row->mid;
        $data['status'] = $row->payment_status;
        $data['min_addmoney'] = $this->system_config['min_addmoney'];
        $data['payment_description'] = '';
        $data['secret_key'] = $row->mid;
        $data['public_key'] = $row->mkey;
        $data['payment'] = $row->payment_name;
        $data['app_id'] = '';
        $data['secret_key'] = '';
        if ($row->payment_name == 'Offline') {
            $data['payment_description'] = $row->payment_description;
        } elseif ($row->payment_name == 'Cashfree') {
            $data['app_id'] = $row->mid;
            $data['secret_key'] = $row->mkey;
        }
        echo json_encode($data,JSON_UNESCAPED_UNICODE);
        exit;
    }



        public function transaction() {
        $data['transaction'] = DB::table('accountstatement as a')
                ->where('a.member_id', Auth::user()->member_id)
                ->select('a.account_statement_id as transaction_id', 'a.note', 'a.join_money', 'a.win_money', 'a.match_id', 'a.note_id', 'a.accountstatement_dateCreated as date', 'a.deposit', 'a.withdraw')
                ->orderBy('account_statement_id', 'DESC')
                ->get();

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function addMoney(Request $request) {
        Cache::flush();
//        $currency = DB::table('currency')
//                ->where("currency_id", $this->system_config['currency'])
//                ->first();
        $row = DB::table('pg_detail')
                        ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                        ->where('payment_name', $request->input('payment_name'))
                        ->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')->first();
        $data['web_config']['currency'] = $row->currency_code;
        
            $validator = Validator::make($request->all(), [
                'payment_name' => 'required',
                'TXN_AMOUNT' => 'required|numeric|min:' . $this->system_config['min_addmoney'],
                'CUST_ID' => 'required'], ['TXN_AMOUNT.required' => trans('message.err_amount_req'),
                'TXN_AMOUNT.min' => trans('message.err_amount_min', ['currency' => $row->currency_symbol, 'amount' => $this->system_config['min_addmoney']]),
                'CUST_ID.required' => trans('message.err_member_id')]);
       
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
//        $row = DB::table('pg_detail')->where('payment_name', $request->input('payment_name'))->first();
        if ($row->payment_name == 'Instamojo') {
            if ($row->payment_status == 'Test')
                $api_url = 'https://test.instamojo.com/';
            else
                $api_url = 'https://api.instamojo.com/';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url . 'oauth2/token/');
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

            $payload = Array(
                'grant_type' => 'client_credentials',
                'client_id' => $row->mid,
                'client_secret' => $row->mkey,
            );

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            $response1 = json_decode(curl_exec($ch), true);
            curl_close($ch);
            $access_token = $response1["access_token"];

            $deposit_data = [
                'member_id' => $request->input('CUST_ID'),
                'deposit_amount' => $request->input('TXN_AMOUNT'),
                'deposit_status' => '0',
                'deposit_by' => $request->input('payment_name'),
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
                
            $deposit_id = DB::table('deposit')->insertGetId($deposit_data);

            $member = DB::table('member')
                    ->where('member_id', $request->input('CUST_ID'))
                    ->first();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url . 'v2/payment_requests/');
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $access_token));
            $payload = array(
                'buyer_name' => $member->user_name,
                'email' => $member->email_id,
                'phone' => $member->mobile_no,
                'purpose' => 'Add to Wallet',
                'amount' => $request->input('TXN_AMOUNT') / $row->currency_point,
                'redirect_url' => $api_url . 'integrations/android/redirect/',
                'send_email' => false,
                'allow_repeated_payments' => false
            );

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            $response2 = json_decode(curl_exec($ch), true);
            curl_close($ch);
            
            if(isset($response2['id'])) {
                $payment_request_id = $response2['id'];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $api_url . 'v2/gateway/orders/payment-request/');
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $access_token));
                $payload = Array(
                    'id' => $payment_request_id
                );
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                $response3 = json_decode(curl_exec($ch), true);
                curl_close($ch);

                $array['status'] = 'true';
                $array['title'] = 'Success!';
                $array['message'] = 'success';
                $array['order_id'] = $response3['order_id'];
                $array['deposit_id'] = $deposit_id;
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = 'error';
                $array['order_id'] = '0';
                $array['deposit_id'] = $deposit_id;
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        }elseif ($row->payment_name == 'Razorpay') {
            $api = new Api($row->mid, $row->mkey);
            $deposit_data = [
                'member_id' => $request->input('CUST_ID'),
                'deposit_amount' => ($request->input('TXN_AMOUNT')) / 100,
                'deposit_status' => '0',
                'deposit_by' => $request->input('payment_name'),
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            $id = DB::table('deposit')->insertGetId($deposit_data);

            $order = $api->order->create(array(
                'receipt' => $id,
                'amount' => $request->input('TXN_AMOUNT') / $row->currency_point,
                'payment_capture' => 1,
                'currency' => $row->currency_code
                    )
            );

            $array['status'] = 'true';
            $array['title'] = 'Success!';
            $array['message'] = 'success';
            $array['order_id'] = $order['id'];
            $array['receipt'] = $id;
            $array['currency'] = $row->currency_code;
            $array['key_id'] = $row->mid;
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        } elseif ($row->payment_name == 'Cashfree') {
            $deposit_data = [
                'member_id' => $request->input('CUST_ID'),
                'deposit_amount' => $request->input('TXN_AMOUNT'),
                'deposit_status' => '0',
                'deposit_by' => $request->input('payment_name'),
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            $id = DB::table('deposit')->insertGetId($deposit_data);
            $cashfreeParams = array(
                'orderId' => $id,
                'orderAmount' => (double) $request->input('TXN_AMOUNT') / $row->currency_point,
                'orderCurrency' => $row->currency_code,
            );
            $postData = json_encode($cashfreeParams);
            $connection = curl_init();
            if ($row->payment_status == 'Production')
                $transactionURL = "https://api.cashfree.com/api/v2/cftoken/order"; // for production
            else
                $transactionURL = "https://test.cashfree.com/api/v2/cftoken/order";
            curl_setopt($connection, CURLOPT_URL, $transactionURL);
            curl_setopt($connection, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($connection, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($connection, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($connection, CURLOPT_HTTPHEADER, array(
                "Content-Type: application/json",
                "x-client-id: " . $row->mid,
                "x-client-secret: " . $row->mkey
            ));
            $response = curl_exec($connection);
            curl_close($connection);
            $response = json_decode($response, true);
            $array = array();
            if ($response['status'] == 'OK') {
                $send_resp = array(
                    'order_id' => $id,
                    'cftoken' => $response['cftoken'],
                );
                $array['status'] = 'true';
                $array['title'] = 'success!';
                $array['message'] = $send_resp;
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = $response;
            }
            echo json_encode($array, JSON_UNESCAPED_SLASHES);
        } elseif ($row->payment_name == 'Google Pay') {
            $bank_transection_no = str_replace(".", "", microtime(true)) . rand(000, 999);
            $deposit_data = [
                'member_id' => $request->input('CUST_ID'),
                'deposit_amount' => $request->input('TXN_AMOUNT'),
                'bank_transection_no' => $bank_transection_no,
                'deposit_status' => '0',
                'deposit_by' => $request->input('payment_name'),
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            $id = DB::table('deposit')->insertGetId($deposit_data);
            if ($id > 0) {
                $array['status'] = 'true';
                $array['title'] = 'success!';
                $array['order_id'] = $id;
                $array['transection_no'] = $bank_transection_no;
                $array['message'] = 'success';
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = 'fail';
            }
            echo json_encode($array, JSON_UNESCAPED_SLASHES);
        } elseif ($row->payment_name == 'PayU') {

            $transaction_id = str_replace(".", "", microtime(true)) . rand(000, 999);
                                                 
            $deposit_data = [
                'member_id' => $request->input('CUST_ID'),
                'deposit_amount' => $request->input('TXN_AMOUNT'),
                'deposit_status' => '0',
                'deposit_by' => $request->input('payment_name'),
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            $id = DB::table('deposit')->insertGetId($deposit_data);
            if ($id > 0) {
                $array['status'] = 'true';
                $array['title'] = 'success!';
                $array['order_id'] = $id;                
                $array['transaction_id'] = $transaction_id;
                $array['message'] = 'success';
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = 'fail';
            }
            echo json_encode($array, JSON_UNESCAPED_SLASHES);
        } elseif ($row->payment_name == 'Tron') {                

                    $deposit_data = [
                        'member_id' => $request->input('CUST_ID'),
                        'deposit_amount' => $request->input('TXN_AMOUNT'),
                        'deposit_status' => '0',
                        'deposit_by' => $request->input('payment_name'),                
                        'entry_from' => '1',
                        'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                    ];

                    $id = DB::table('deposit')->insertGetId($deposit_data);

                    include ('./tron/vendor/autoload.php');                                            
                    
                    if($row->payment_status == 'Test') {
                        $tron_api_url = 'https://api.shasta.trongrid.io';
                    } else {
                        $tron_api_url = 'https://api.trongrid.io';
                    }
        
                    $fullNode = new \IEXBase\TronAPI\Provider\HttpProvider($tron_api_url);
                    $solidityNode = new \IEXBase\TronAPI\Provider\HttpProvider($tron_api_url);
                    $eventServer = new \IEXBase\TronAPI\Provider\HttpProvider($tron_api_url);
        
                    try {
                        $tron = new Tron($fullNode, $solidityNode, $eventServer, null, null); 
                    } catch (\IEXBase\TronAPI\Exception\TronException $e) {                                                                                                
                        $array['status'] = 'false';
                        $array['title'] = 'Error!';
                        $array['message'] = $e->getMessage(); 
                        echo json_encode($array, JSON_UNESCAPED_SLASHES);  
                        exit;             
                    }

                    try {

                        $account = $tron->createAccount(); 
            
                        $data['wallet_address'] = $account->getAddress(true);
                        $data['address_hex']    = $account->getAddress();
                        $data['private_key']    = $account->getPrivateKey();
                        $data['public_key']     = $account->getPublicKey();
                        
                        $update_deposit = array(
                            'wallet_address' => $data['wallet_address'],
                            'address_hex' => $data['address_hex'],                        
                            'private_key' => $data['private_key'],
                            'public_key' => $data['public_key'],                                
                        );
                        
                        $res = DB::table('deposit')->where('deposit_id', $id)->update($update_deposit);                            
                        
                        $array['status'] = 'true';
                        $array['order_id'] = $id;
                        $array['wallet_address'] = $data['wallet_address'];
                        $array['title'] = 'Success!';
                        $array['message'] = trans('message.text_money_requested');
                        echo json_encode($array, JSON_UNESCAPED_SLASHES);
                        exit;
                                                        
                    } catch (\IEXBase\TronAPI\Exception\TronException $e) {                                                 
                        $array['status'] = 'false';
                        $array['title'] = 'Error!';
                        $array['message'] = $e->getMessage();   
                        echo json_encode($array, JSON_UNESCAPED_SLASHES);
                        exit;
                    }                  
        } elseif ($row->payment_name == 'ShadowPay') {
            $deposit_data = [
                'member_id' => $request->input('CUST_ID'),
                'deposit_amount' => $request->input('TXN_AMOUNT'),
                'deposit_status' => '0',
                'deposit_by' => $request->input('payment_name'),
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            $id = DB::table('deposit')->insertGetId($deposit_data);
            
            // Get member details
            $member = DB::table('member')
                    ->where('member_id', $request->input('CUST_ID'))
                    ->first();
            
            // Generate unique order ID
            $order_id = $id . time() . rand(100, 999);
            
            // Prepare ShadowPay API request
            $shadowpay_params = array(
                'customer_mobile' => $member->mobile_no,
                'user_token' => $row->mid,  // Fetch API token from database
                'amount' => $request->input('TXN_AMOUNT'),
                'order_id' => $order_id,
                'redirect_url' => url('/api/shadowpay_response'),
                'remark1' => 'Wallet Recharge',
                'remark2' => 'Member ID: ' . $request->input('CUST_ID')
            );
            
            // Update deposit with transaction_id (using existing column)
            DB::table('deposit')->where('deposit_id', $id)->update(['transaction_id' => $order_id]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://pay.shadowlink.in/api/create-order');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($shadowpay_params));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/x-www-form-urlencoded'
            ));
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $response_data = json_decode($response, true);
            
            if ($response_data && $response_data['status'] == true) {
                $array['status'] = 'true';
                $array['title'] = 'Success!';
                $array['message'] = 'Order created successfully';
                $array['order_id'] = $id;
                $array['shadowpay_order_id'] = $response_data['result']['orderId'];
                $array['payment_url'] = $response_data['result']['payment_url'];
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = isset($response_data['message']) ? $response_data['message'] : 'Failed to create order';
                $array['order_id'] = $id;
            }
            
            echo json_encode($array, JSON_UNESCAPED_SLASHES);
        } else {
            $deposit_data = [
                'member_id' => $request->input('CUST_ID'),
                'deposit_amount' => $request->input('TXN_AMOUNT'),
                'deposit_status' => '0',
                'deposit_by' => $request->input('payment_name'),
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            $id = DB::table('deposit')->insertGetId($deposit_data);
            if ($row->payment_name == 'Offline') {
                if ($id != 0) {
                    $array['status'] = 'true';
                    $array['title'] = 'Success!';
                    $array['message'] = trans('message.text_money_requested');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                $_POST['CUST_ID'] = $request->input('CUST_ID');
                $_POST['TXN_AMOUNT'] = (float) sprintf('%.2F', $request->input('TXN_AMOUNT') / $row->currency_point);
                $_POST['CALLBACK_URL'] = $request->input('CALLBACK_URL');
                $_POST['CHANNEL_ID'] = $request->input('CHANNEL_ID');
                $_POST['ORDER_ID'] = $id;
                $_POST['MID'] = $row->mid;
                $_POST['INDUSTRY_TYPE_ID'] = $row->itype;
                $_POST['WEBSITE'] = $row->wname;
                define('PAYTM_MERCHANT_KEY', $row->mkey);
                define('PAYTM_MERCHANT_ID', $row->mid);
                header("Pragma: no-cache");
                header("Cache-Control: no-cache");
                header("Expires: 0");
                require_once "./lib/encdec_paytm.php";
                $checkSum = "";
                $findme = 'REFUND';
                $findmepipe = '|';
                $paramList = array();
                $paramList["MID"] = '';
                $paramList["ORDER_ID"] = '';
                $paramList["CUST_ID"] = '';
                $paramList["INDUSTRY_TYPE_ID"] = '';
                $paramList["CHANNEL_ID"] = '';
                $paramList["TXN_AMOUNT"] = '';
                $paramList["WEBSITE"] = '';
                foreach ($_POST as $key => $value) {
                    $pos = strpos($value, $findme);
                    $pospipe = strpos($value, $findmepipe);
                    if ($pos === false || $pospipe === false) {
                        $paramList[$key] = $value;
                    }
                }
                $checkSum = getChecksumFromArray($paramList, PAYTM_MERCHANT_KEY);
                $array = array();
                $array['status'] = 'true';
                $array['title'] = 'success!';
                $array['message'] = array("CHECKSUMHASH" => $checkSum, "ORDER_ID" => $_POST["ORDER_ID"], "MID" => $_POST["MID"], "INDUSTRY_TYPE_ID" => $_POST["INDUSTRY_TYPE_ID"], "WEBSITE" => $_POST["WEBSITE"], "payt_STATUS" => "1");
                echo json_encode($array, JSON_UNESCAPED_SLASHES);
            }
        }
    }
    
    public function payuSuccFail(Request $request) {
        echo '<center><h3>Please Wait</h3></center>';die();
    }
    
    public function payuResponse(Request $request) {
        $validator = Validator::make($request->all(), [
                    'member_id' => 'required',
                    'amount' => 'required|numeric|min:' . $this->system_config['min_addmoney'],
                    'order_id' => 'required',
                    'status' => 'required',
        ]);
        if ($validator->fails()) {

            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;

        }
        $pg_detail = DB::table('pg_detail')->where('payment_name', $request->input('payment_name'))
                        ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                        ->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')->first();
        $order = DB::table('deposit')
                ->where('deposit_id', $request->input('order_id'))
                ->first();
        if ($request->input('status') == "true") {
            
            if ($pg_detail->payment_status == 'Production'){
                $url = "https://info.payu.in/merchant/postservice.php?form=2";
            } else {
                $url = "https://test.payu.in/merchant/postservice.php?form=2";
            }
                    
            $hash = hash('SHA512',$pg_detail->mkey . '|verify_payment|' . $request->input('custom_transaction_id') . '|' . $pg_detail->wname);
            
            $param = ["key"=>$pg_detail->mkey,"command"=>"verify_payment","var1"=>$request->input('custom_transaction_id'),"hash" => $hash];
		
    	    $ch = curl_init ( $url );
    	    curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, true );
    	    curl_setopt ( $ch, CURLOPT_HEADER, array(
                "accept : application/json",
                "Content-Type : application/x-www-form-urlencoded"
            ) );
    	    curl_setopt ( $ch, CURLOPT_AUTOREFERER, true );
    	    curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 120 );
    	    curl_setopt ( $ch, CURLOPT_TIMEOUT, 120 );
    	    curl_setopt ( $ch, CURLOPT_MAXREDIRS, 10 );
    	    curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false );
    	    curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
    	    curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
    	    curl_setopt ($ch, CURLOPT_HEADER, 0);
    	    curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );        	    
    	    curl_setopt($ch, CURLOPT_POST, 1);
    	    curl_setopt($ch, CURLOPT_POSTFIELDS,  http_build_query($param));
    	    $checking_result = curl_exec ( $ch );    	    
    	    $errorNo = curl_errno ( $ch );
    	    $errorMsg = curl_error ( $ch );
    	    curl_close ( $ch );
    	    
    	    $checking_result = json_decode($checking_result,true); 
    	    
    	    if($checking_result['status'] == 1 && $checking_result['transaction_details'][$request->input('custom_transaction_id')]['status'] == 'success') {
    	        
                if($order->deposit_status == 0) {
                    $deposit_data = [
                        'deposit_status' => '1','bank_transection_no' => $request->input('transaction_no')];
                    $res = DB::table('deposit')->where('deposit_id', $request->input('order_id'))->update($deposit_data);
                    $row = DB::table('member')
                            ->where('member_id', $request->input('member_id'))
                            ->first();
                    $join_money = $row->join_money + ($request->input('amount'));
                    $browser = '';
                    $agent = new Agent();
                    if ($agent->isMobile()) {
                        $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                    } elseif ($agent->isDesktop()) {
                        $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                    } elseif ($agent->isRobot()) {
                        $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                    }
                    $ip = $this->getIp();
                    $acc_data = [
                        'member_id' => $request->input('member_id'),
                        'pubg_id' => $row->pubg_id,
                        'deposit' => $request->input('amount'),
                        'withdraw' => 0,
                        'join_money' => $join_money,
                        'win_money' => $row->wallet_balance,
                        'note' => 'Add Money to Join Wallet',
                        'note_id' => '0',
                        'entry_from' => '1',
                        'ip_detail' => $ip,
                        'browser' => $browser,
                        'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                    ];
                    DB::table('accountstatement')->insertGetId($acc_data);
    
                    $upd_data = [
                        'join_money' => $join_money];
                    DB::table('member')->where('member_id', $request->input('member_id'))->update($upd_data);
    
                    $array['status'] = 'true';
                    $array['title'] = 'Success!';
                    $array['message'] = trans('message.text_succ_balance_added');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                 
                $deposit_data = [
                'deposit_status' => '2','bank_transection_no' => $request->input('transaction_no')];
                $res = DB::table('deposit')->where('deposit_id', $request->input('order_id'))->update($deposit_data);
    
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.text_err_balance_not_add');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            } 
        } else {
            
            $deposit_data = [
                'deposit_status' => '2','bank_transection_no' => $request->input('transaction_no')];
            $res = DB::table('deposit')->where('deposit_id', $request->input('order_id'))->update($deposit_data);

            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.text_err_balance_not_add');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    public function verifyChecksum(Request $request) {

        header("Pragma: no-cache");
        header("Cache-Control: no-cache");
        header("Expires: 0");

        $pg_detail = DB::table('pg_detail')
                ->first();
        define('PAYTM_MERCHANT_KEY', $pg_detail->mkey);
        define('PAYTM_MERCHANT_ID', $pg_detail->mid);
        require_once "./lib/encdec_paytm.php";

        $paytmChecksum = "";
        $paramList = array();
        $isValidChecksum = FALSE;

        $paramList = $_POST;
        $return_array = $_POST;
        $paytmChecksum = isset($_POST["CHECKSUMHASH"]) ? $_POST["CHECKSUMHASH"] : ""; //Sent by Paytm pg
        $isValidChecksum = verifychecksum_e($paramList, PAYTM_MERCHANT_KEY, $paytmChecksum); //will return TRUE or FALSE string.


        $return_array["IS_CHECKSUM_VALID"] = $isValidChecksum ? "Y" : "N";
        unset($return_array["CHECKSUMHASH"]);

        $encoded_json = htmlentities(json_encode($return_array));
        echo '<html>' .
        '<head>' .
        '<meta http-equiv="Content-Type" content="text/html;charset=ISO-8859-I">' .
        '<title>Paytm</title>' .
        '<script type="text/javascript">' .
        'function response(){' .
        'return document.getElementById("response").value;' .
        '}' .
        '</script>' .
        '</head>' .
        '<body>' .
        'Redirect back to the app<br>' .
        '<form name="frm" method="post">' .
        '<input type="hidden" id="response" name="responseField" value="' . $encoded_json . '">' .
        '</form>' .
        '</body>' .
        '</html>';
    }

    public function paytmResponse(Request $request) {
        $validator = Validator::make($request->all(), [
                    'status' => 'required',
                    'order_id' => 'required',
                    'banktransectionno' => 'required',
                    'reason' => 'required',
                    'amount' => 'required',
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
        if ($request->input('status') == '1' || $request->input('status') == 1) {
            $data = DB::table('deposit')
                    ->where('deposit_id', $request->input('order_id'))
                    ->first();
            $pg_detail = DB::table('pg_detail')
                    ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                    ->where('payment_name', 'PayTm')
                    ->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')
                    ->first();
            define('PAYTM_MERCHANT_KEY', $pg_detail->mkey);
            define('PAYTM_MERCHANT_ID', $pg_detail->mid);
            require_once("./lib/encdec_paytm.php");

            $orderId = $request->input('order_id');
            $merchantMid = PAYTM_MERCHANT_ID;
            $merchantKey = PAYTM_MERCHANT_KEY;
            $paytmParams["MID"] = $merchantMid;
            $paytmParams["ORDERID"] = $orderId;
            $paytmChecksum = getChecksumFromArray($paytmParams, $merchantKey);
            $paytmParams['CHECKSUMHASH'] = urlencode($paytmChecksum);
            $postData = "JsonData=" . json_encode($paytmParams, JSON_UNESCAPED_SLASHES);
            $connection = curl_init(); // initiate curl
            if ($pg_detail->payment_status == 'Production')
                $transactionURL = "https://securegw.paytm.in/merchant-status/getTxnStatus"; // for production
            else
                $transactionURL = "https://securegw-stage.paytm.in/merchant-status/getTxnStatus";
            curl_setopt($connection, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($connection, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($connection, CURLOPT_URL, $transactionURL);
            curl_setopt($connection, CURLOPT_POST, true);
            curl_setopt($connection, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($connection, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($connection, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            $responseReader = curl_exec($connection);
            $responseData = json_decode($responseReader, true);
            if (($responseData['STATUS'] == 'TXN_SUCCESS')) {
                if ($data->deposit_status == '0' || $data->deposit_status == '2') {
                    $deposit_data = [
                        'bank_transection_no' => $request->input('banktransectionno'),
                        'deposit_status' => '1',
                        'reason' => $request->input('reason')];
                    DB::table('deposit')->where('deposit_id', $request->input('order_id'))->update($deposit_data);

                    $row = DB::table('member')
                            ->where('member_id', $data->member_id)
                            ->first();
                    $join_money = $row->join_money + $request->input('amount');
                    $browser = '';
                    $agent = new Agent();
                    if ($agent->isMobile()) {
                        $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                    } elseif ($agent->isDesktop()) {
                        $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                    } elseif ($agent->isRobot()) {
                        $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                    }
                    $ip = $this->getIp();
                    $acc_data = [
                        'member_id' => $data->member_id,
                        'pubg_id' => $row->pubg_id,
                        'deposit' => $request->input('amount'),
                        'withdraw' => 0,
                        'join_money' => $join_money,
                        'win_money' => $row->wallet_balance,
                        'note' => 'Add Money to Join Wallet',
                        'note_id' => '0',
                        'entry_from' => '1',
                        'ip_detail' => $ip,
                        'browser' => $browser,
                        'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                    ];
                    DB::table('accountstatement')->insertGetId($acc_data);

                    $upd_data = [
                        'join_money' => $join_money];
                    DB::table('member')->where('member_id', $row->member_id)->update($upd_data);

                    $array['status'] = 'true';
                    $array['title'] = 'Success!';
                    $array['message'] = trans('message.text_succ_balance_added');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $array['status'] = 'false';
                    $array['title'] = 'Error!';
                    $array['message'] = trans('message.text_err_balance_already_added');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                $deposit_data = [
                    'bank_transection_no' => $request->input('banktransectionno'),
                    'deposit_status' => '2',
                ];
                DB::table('deposit')->where('deposit_id', $request->input('order_id'))->update($deposit_data);

                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.text_err_balance_not_add');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $deposit_data = [
                'bank_transection_no' => $request->input('banktransectionno'),
                'deposit_status' => '2',
            ];
            DB::table('deposit')->where('deposit_id', $request->input('order_id'))->update($deposit_data);

            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.text_err_balance_not_add');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function paypalResponse(Request $request) {
        $validator = Validator::make($request->all(), [
                    'member_id' => 'required',
                    'state' => 'required',
                    'id' => 'required',
                    'amount' => 'required|numeric|min:' . $this->system_config['min_addmoney'],
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
        $pg_detail = DB::table('pg_detail')
                ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                ->where('payment_name', 'PayPal')
                ->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')
                ->first();
        if ($request->input('state') == 'approved') {
            $deposit_data = [
                'member_id' => $request->input('member_id'),
                'deposit_amount' => $request->input('amount'),
                'bank_transection_no' => $request->input('id'),
                'deposit_status' => '1',
                'deposit_by' => 'PayPal',
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            DB::table('deposit')->insert($deposit_data);

            $row = DB::table('member')
                    ->where('member_id', $request->input('member_id'))
                    ->first();
            $join_money = $row->join_money + ($request->input('amount'));
            $browser = '';
            $agent = new Agent();
            if ($agent->isMobile()) {
                $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
            } elseif ($agent->isDesktop()) {
                $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
            } elseif ($agent->isRobot()) {
                $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
            }
            $ip = $this->getIp();
            $acc_data = [
                'member_id' => $request->input('member_id'),
                'pubg_id' => $row->pubg_id,
                'deposit' => $request->input('amount'),
                'withdraw' => 0,
                'join_money' => $join_money,
                'win_money' => $row->wallet_balance,
                'note' => 'Add Money to Join Wallet',
                'note_id' => '0',
                'entry_from' => '1',
                'ip_detail' => $ip,
                'browser' => $browser,
                'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            DB::table('accountstatement')->insertGetId($acc_data);

            $upd_data = [
                'join_money' => $join_money];
            DB::table('member')->where('member_id', $request->input('member_id'))->update($upd_data);

            $array['status'] = 'true';
            $array['title'] = 'Success!';
            $array['message'] = trans('message.text_succ_balance_added');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $deposit_data = [
                'member_id' => $request->input('member_id'),
                'deposit_amount' => $request->input('amount'),
                'bank_transection_no' => $request->input('id'),
                'deposit_status' => '2',
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            DB::table('deposit')->insert($deposit_data);

            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.text_err_balance_not_add');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function paystackResponse(Request $request) {
        $pg_detail = DB::table('pg_detail')->where('payment_name', 'PayStack')
                        ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                        ->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')->first();
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . $request->input('reference'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . $pg_detail->mid
            ),
        ));
        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        if ($response['status'] == true) {
            $deposit_data = [
                'member_id' => Auth::user()->member_id,
                'deposit_amount' => $request->input('amount'),
                'bank_transection_no' => $request->input('reference'),
                'deposit_status' => '1',
                'deposit_by' => 'PayStack',
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            DB::table('deposit')->insert($deposit_data);

            $row = DB::table('member')
                    ->where('member_id', Auth::user()->member_id)
                    ->first();
            $join_money = $row->join_money + $request->input('amount');
            $browser = '';
            $agent = new Agent();
            if ($agent->isMobile()) {
                $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
            } elseif ($agent->isDesktop()) {
                $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
            } elseif ($agent->isRobot()) {
                $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
            }
            $ip = $this->getIp();
            $acc_data = [
                'member_id' => Auth::user()->member_id,
                'pubg_id' => $row->pubg_id,
                'deposit' => $request->input('amount'),
                'withdraw' => 0,
                'join_money' => $join_money,
                'win_money' => $row->wallet_balance,
                'note' => 'Add Money to Join Wallet',
                'note_id' => '0',
                'entry_from' => '1',
                'ip_detail' => $ip,
                'browser' => $browser,
                'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            DB::table('accountstatement')->insertGetId($acc_data);

            $upd_data = [
                'join_money' => $join_money];
            DB::table('member')->where('member_id', Auth::user()->member_id)->update($upd_data);

            $array['status'] = 'true';
            $array['title'] = 'Success!';
            $array['message'] = trans('message.text_succ_balance_added');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $deposit_data = [
                'member_id' => Auth::user()->member_id,
                'deposit_amount' => $response['data']['amount'] / $pg_detail->currency_point,
                'bank_transection_no' => $request->input('reference'),
                'deposit_status' => '2',
                'deposit_by' => 'PayStack',
                'entry_from' => '1',
                'deposit_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            DB::table('deposit')->insert($deposit_data);

            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.text_err_balance_not_add');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function instamojoResponse(Request $request) {
        $validator = Validator::make($request->all(), [
                    'member_id' => 'required',
                    'amount' => 'required|numeric|min:' . $this->system_config['min_addmoney'],
                    'status' => 'required',
                    'payment_id' => 'required',
                    'order_id' => 'required',
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
        if ($request->input('status') == 'Credit') {
            $pg_detail = DB::table('pg_detail')->where('payment_name', 'Instamojo')
                            ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                            ->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')->first();
            $data = DB::table('deposit')
                    ->where('deposit_id', $request->input('order_id'))
                    ->first();
            if ($data->deposit_status == '0' || $data->deposit_status == '2') {
                $deposit_data = [
                    'bank_transection_no' => $request->input('payment_id'),
                    'deposit_status' => '1',];
                DB::table('deposit')->where('deposit_id', $request->input('order_id'))->update($deposit_data);

                $row = DB::table('member')
                        ->where('member_id', $request->input('member_id'))
                        ->first();
                $join_money = $row->join_money + ($request->input('amount'));
                $browser = '';
                $agent = new Agent();
                if ($agent->isMobile()) {
                    $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                } elseif ($agent->isDesktop()) {
                    $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                } elseif ($agent->isRobot()) {
                    $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                }
                $ip = $this->getIp();
                $acc_data = [
                    'member_id' => $request->input('member_id'),
                    'pubg_id' => $row->pubg_id,
                    'deposit' => $request->input('amount'),
                    'withdraw' => 0,
                    'join_money' => $join_money,
                    'win_money' => $row->wallet_balance,
                    'note' => 'Add Money to Join Wallet',
                    'note_id' => '0',
                    'entry_from' => '1',
                    'ip_detail' => $ip,
                    'browser' => $browser,
                    'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                ];
                DB::table('accountstatement')->insertGetId($acc_data);

                $upd_data = [
                    'join_money' => $join_money];
                DB::table('member')->where('member_id', $request->input('member_id'))->update($upd_data);

                $array['status'] = 'true';
                $array['title'] = 'Success!';
                $array['message'] = trans('message.text_succ_balance_added');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.text_err_balance_already_added');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $deposit_data = [
                'bank_transection_no' => $request->input('payment_id'),
                'deposit_status' => '2',];
            DB::table('deposit')->where('deposit_id', $request->input('order_id'))->update($deposit_data);

            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.text_err_balance_not_add');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function razorpayResponse(Request $request) {
        $validator = Validator::make($request->all(), [
                    'member_id' => 'required',
                    'amount' => 'required|numeric|min:' . $this->system_config['min_addmoney'],
                    'receipt' => 'required',
                    'razorpay_order_id' => 'required',
                    'razorpay_payment_id' => 'required',
                    'razorpay_signature' => 'required',
                    'status' => 'required',
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
        $pg_detail = DB::table('pg_detail')->where('payment_name', 'Razorpay')
                        ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                        ->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')->first();
        try {
            $api = new Api($pg_detail->mid, $pg_detail->mkey);
            $attributes = array(
                'razorpay_signature' => $request->input('razorpay_signature'),
                'razorpay_payment_id' => $request->input('razorpay_payment_id'),
                'razorpay_order_id' => $request->input('razorpay_order_id')
            );
            $order = $api->utility->verifyPaymentSignature($attributes);
            if ($request->input('status') == "true") {
                $data = DB::table('deposit')
                        ->where('deposit_id', $request->input('receipt'))
                        ->first();
                if ($data->deposit_status == '0' || $data->deposit_status == '2') {
                    $deposit_data = [
                        'bank_transection_no' => $request->input('razorpay_payment_id'),
                        'deposit_status' => '1',];
                    DB::table('deposit')->where('deposit_id', $request->input('receipt'))->update($deposit_data);

                    $row = DB::table('member')
                            ->where('member_id', $request->input('member_id'))
                            ->first();
                    $join_money = $row->join_money + ($request->input('amount'));
                    $browser = '';
                    $agent = new Agent();
                    if ($agent->isMobile()) {
                        $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                    } elseif ($agent->isDesktop()) {
                        $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                    } elseif ($agent->isRobot()) {
                        $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                    }
                    $ip = $this->getIp();
                    $acc_data = [
                        'member_id' => $request->input('member_id'),
                        'pubg_id' => $row->pubg_id,
                        'deposit' => $request->input('amount'),
                        'withdraw' => 0,
                        'join_money' => $join_money,
                        'win_money' => $row->wallet_balance,
                        'note' => 'Add Money to Join Wallet',
                        'note_id' => '0',
                        'entry_from' => '1',
                        'ip_detail' => $ip,
                        'browser' => $browser,
                        'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                    ];
                    DB::table('accountstatement')->insertGetId($acc_data);

                    $upd_data = [
                        'join_money' => $join_money];
                    DB::table('member')->where('member_id', $request->input('member_id'))->update($upd_data);

                    $array['status'] = 'true';
                    $array['title'] = 'Success!';
                    $array['message'] = trans('message.text_succ_balance_added');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $array['status'] = 'false';
                    $array['title'] = 'Error!';
                    $array['message'] = trans('message.text_err_balance_already_added');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } else {
                $deposit_data = [
                    'bank_transection_no' => $request->input('razorpay_payment_id'),
                    'deposit_status' => '2',
                ];
                DB::table('deposit')->where('deposit_id', $request->input('receipt'))->update($deposit_data);

                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.text_err_balance_not_add');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (Exception $e) {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.text_err_balance_not_add');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function cashFreeResponse(Request $request) {
        $validator = Validator::make($request->all(), [
                    'txStatus' => 'required',
                    'orderId' => 'required',
                    'referenceId' => 'required',
                    'txMsg' => 'required',
                    'orderAmount' => 'required',
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
        if ($request->input('txStatus') == 'SUCCESS') {
            $pg_detail = DB::table('pg_detail')->where('payment_name', 'Cashfree')
                            ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                            ->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')->first();
            $data = DB::table('deposit')
                    ->where('deposit_id', $request->input('orderId'))
                    ->first();
            if ($data->deposit_status == '0' || $data->deposit_status == '2') {
                $deposit_data = [
                    'bank_transection_no' => $request->input('referenceId'),
                    'deposit_status' => '1',
                    'reason' => $request->input('txMsg')];
                DB::table('deposit')->where('deposit_id', $request->input('orderId'))->update($deposit_data);

                $row = DB::table('member')
                        ->where('member_id', $data->member_id)
                        ->first();
                $join_money = $row->join_money + $data->deposit_amount;
                $browser = '';
                $agent = new Agent();
                if ($agent->isMobile()) {
                    $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                } elseif ($agent->isDesktop()) {
                    $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                } elseif ($agent->isRobot()) {
                    $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                }
                $ip = $this->getIp();
                $acc_data = [
                    'member_id' => $data->member_id,
                    'pubg_id' => $row->pubg_id,
                    'deposit' => $data->deposit_amount,
                    'withdraw' => 0,
                    'join_money' => $join_money,
                    'win_money' => $row->wallet_balance,
                    'note' => 'Add Money to Join Wallet',
                    'note_id' => '0',
                    'entry_from' => '1',
                    'ip_detail' => $ip,
                    'browser' => $browser,
                    'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                ];
                DB::table('accountstatement')->insertGetId($acc_data);

                $upd_data = [
                    'join_money' => $join_money];
                DB::table('member')->where('member_id', $row->member_id)->update($upd_data);

                $array['status'] = 'true';
                $array['title'] = 'Success!';
                $array['message'] = trans('message.text_succ_balance_added');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.text_err_balance_already_added');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $deposit_data = [
                'bank_transection_no' => $request->input('referenceId'),
                'deposit_status' => '2',
            ];
            DB::table('deposit')->where('deposit_id', $request->input('orderId'))->update($deposit_data);

            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = 'Transaction failed';
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function googlePayResponse(Request $request) {
        $validator = Validator::make($request->all(), [
                    'member_id' => 'required',
                    'amount' => 'required|numeric|min:' . $this->system_config['min_addmoney'],
                    'transaction_id' => 'required',
                    'order_id' => 'required',
                    'status' => 'required',
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
        $pg_detail = DB::table('pg_detail')->where('payment_name', 'Cashfree')
                        ->leftJoin('currency as c', 'c.currency_id', '=', 'pg_detail.currency')
                        ->select('pg_detail.*', 'c.currency_name', 'c.currency_code', 'c.currency_symbol')->first();
        $order = DB::table('deposit')
                ->where('bank_transection_no', $request->input('transaction_id'))
                ->where('deposit_id', $request->input('order_id'))
                ->first();
        if ($request->input('status') == "true" && $order->deposit_status == 0) {
            $deposit_data = [
                'deposit_status' => '1',];
            $res = DB::table('deposit')->where('bank_transection_no', $request->input('transection_no'))->where('deposit_id', $request->input('order_id'))->update($deposit_data);
            $row = DB::table('member')
                    ->where('member_id', $request->input('member_id'))
                    ->first();
            $join_money = $row->join_money + ($request->input('amount'));
            $browser = '';
            $agent = new Agent();
            if ($agent->isMobile()) {
                $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
            } elseif ($agent->isDesktop()) {
                $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
            } elseif ($agent->isRobot()) {
                $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
            }
            $ip = $this->getIp();
            $acc_data = [
                'member_id' => $request->input('member_id'),
                'pubg_id' => $row->pubg_id,
                'deposit' => $request->input('amount'),
                'withdraw' => 0,
                'join_money' => $join_money,
                'win_money' => $row->wallet_balance,
                'note' => 'Add Money to Join Wallet',
                'note_id' => '0',
                'entry_from' => '1',
                'ip_detail' => $ip,
                'browser' => $browser,
                'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            DB::table('accountstatement')->insertGetId($acc_data);

            $upd_data = [
                'join_money' => $join_money];
            DB::table('member')->where('member_id', $request->input('member_id'))->update($upd_data);

            $array['status'] = 'true';
            $array['title'] = 'Success!';
            $array['message'] = trans('message.text_succ_balance_added');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.text_err_balance_not_add');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    public function shadowpayResponse(Request $request) {
        $validator = Validator::make($request->all(), [
                    'order_id' => 'required',
                    'status' => 'required',
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';
            $array['title'] = 'Error!'; 
            $array['message'] = $validator->errors()->first();
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Find the deposit record by transaction_id
        $order = DB::table('deposit')
                ->where('transaction_id', $request->input('order_id'))
                ->where('deposit_by', 'ShadowPay')
                ->first();
                
        if (!$order) {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = 'Order not found';
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Get ShadowPay configuration from database
        $pg_detail = DB::table('pg_detail')
                ->where('payment_name', 'ShadowPay')
                ->first();
        
        if (!$pg_detail) {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = 'ShadowPay configuration not found';
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Check order status with ShadowPay API
        $status_params = array(
            'user_token' => $pg_detail->mid,  // Fetch API token from database
            'order_id' => $request->input('order_id')
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://pay.shadowlink.in/api/check-order-status');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($status_params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded'
        ));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $status_data = json_decode($response, true);
        
        if ($status_data && $status_data['status'] == 'COMPLETED' && $order->deposit_status == 0) {
            // Update deposit status
            $deposit_data = [
                'deposit_status' => '1',
                'transaction_id' => isset($status_data['result']['utr']) ? $status_data['result']['utr'] : $request->input('order_id')
            ];
            $res = DB::table('deposit')->where('deposit_id', $order->deposit_id)->update($deposit_data);
            
            // Get member details
            $row = DB::table('member')
                    ->where('member_id', $order->member_id)
                    ->first();
                    
            // Update member balance
            $join_money = $row->join_money + $order->deposit_amount;
            
            // Get browser info
            $browser = '';
            $agent = new Agent();
            if ($agent->isMobile()) {
                $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
            } elseif ($agent->isDesktop()) {
                $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
            } elseif ($agent->isRobot()) {
                $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
            }
            
            $ip = $this->getIp();
            
            // Add account statement
            $acc_data = [
                'member_id' => $order->member_id,
                'pubg_id' => $row->pubg_id,
                'deposit' => $order->deposit_amount,
                'withdraw' => 0,
                'join_money' => $join_money,
                'win_money' => $row->wallet_balance,
                'note' => 'Add Money to Join Wallet via ShadowPay',
                'note_id' => '0',
                'entry_from' => '1',
                'ip_detail' => $ip,
                'browser' => $browser,
                'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
            ];
            DB::table('accountstatement')->insertGetId($acc_data);

            // Update member balance
            $upd_data = [
                'join_money' => $join_money
            ];
            DB::table('member')->where('member_id', $order->member_id)->update($upd_data);

            $array['status'] = 'true';
            $array['title'] = 'Success!';
            $array['message'] = trans('message.text_succ_balance_added');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = isset($status_data['message']) ? $status_data['message'] : 'Payment failed or already processed';
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function notificationList($game_id) {
        $member_id = Auth::user()->member_id;
        
        $data['notifications'] = DB::table('notifications')
                ->where("member_id", $member_id)
				->where("game_id", $game_id)
                ->orderBy('date_created', 'DESC')
                ->get();
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public function ludoLeaderBoard($game_id) {
        $member_id = Auth::user()->member_id;
        
        $data['list'] = DB::table('ludo_challenge as l')
                ->select('m.member_id','m.first_name','m.last_name', DB::raw('(CASE 
                WHEN profile_image = "" THEN ""
                ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", profile_image) 
                END) AS profile_image'),DB::raw('SUM(winning_price) as total_amount'),DB::raw('count(ludo_challenge_id) as total_challenge'))
                ->leftJoin('member as m', 'm.member_id', '=', 'l.winner_id')
                ->where('challenge_status','3')
				->where('game_id',$game_id)
                ->groupBy('winner_id')
                ->orderBy('total_amount', 'DESC')
                ->get();
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
	
	public function budyList($game_id) {
        $member_id = Auth::user()->member_id;
        
        $member_data = DB::table('member')
                ->where("member_id", $member_id)
                ->select("*")
                ->first();
        
        if($member_data->budy_list == '' || $member_data->budy_list == null) {
            $data['member_list'] = array();
        } else {
            $budy_list = unserialize($member_data->budy_list);
            			
            $data['member_list'] = DB::table('member')
                                    ->whereIn("member_id", $budy_list[$game_id])
                                    ->select("member_id","first_name","last_name","ludo_username",DB::raw('(CASE 
                                        WHEN profile_image = "" THEN ""
                                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", profile_image) 
                                        END) AS profile_image'))
                                    ->get();
        }
        
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
	
	public function budyPlayRequest($to_member_id,$game_id) {
        
        $member_id = Auth::user()->member_id;
        
        $auth_mem_data = DB::table('member')
                ->where("member_id", $member_id)
                ->select("*")
                ->first();
                
        $mem_data = DB::table('member')
                ->where("member_id", $to_member_id)
                ->select("*")
                ->first();
		
		$game_data = DB::table('game')
                ->where("game_id", $game_id)
                ->select("*")
                ->first();
        
        $heading_msg = 'Request to Play';
        $content_msg = $auth_mem_data->first_name . " " . $auth_mem_data->last_name ." Send Play Request to You for Play" . $game_data->game_name . ".";
        
        if($mem_data->push_noti == 1 || $mem_data->push_noti == '1'){
            if($this->send_onesignal_noti($heading_msg,$content_msg,$mem_data->player_id,$mem_data->member_id,$game_id)){                            
                $array['status'] = 'true';
                $array['title'] = 'Success!';
                $array['message'] = trans('message.succ_budy_request');
                echo json_encode($array,JSON_UNESCAPED_SLASHES);
                exit;
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_budy_request');
                echo json_encode($array,JSON_UNESCAPED_SLASHES);
                exit;
            }
        } else {
            $array['status'] = 'false';
            $array['title'] = 'Error!';
            $array['message'] = trans('message.err_budy_request');
            echo json_encode($array,JSON_UNESCAPED_SLASHES);
            exit;
        }
                                                                            
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public function sendChatNotificationNew(Request $request)
    {
        $from_member_id = Auth::user()->member_id;   // sender
        $to_member_id   = $request->input('to_member_id'); // receiver
        $title          = $request->input('title'); // e.g., "Battle Mania V5 LK_161"
        $message        = $request->input('message'); // chat message
        $ludo_auto_id   = $request->input('auto_id'); // auto_id from ludo_challenge table
    
        // Validate required fields
        if (!$to_member_id || !$title || !$message || !$ludo_auto_id) {
            return response()->json([
                "status" => "false",
                "message" => "Missing required parameters"
            ]);
        }
    
        // Fetch sender
        $from_data = DB::table('member')->where('member_id', $from_member_id)->first();
        $sender_name = $from_data ? $from_data->first_name . ' ' . $from_data->last_name : "Unknown";
    
        // Fetch receiver
        $to_data = DB::table('member')->where('member_id', $to_member_id)->first();
        if (!$to_data) {
            return response()->json([
                "status" => "false",
                "message" => "Receiver not found"
            ]);
        }
        $token = $to_data->player_id;
    
        // Fetch game_id
        $ludo_challenge = DB::table('ludo_challenge')
            ->where('auto_id', $ludo_auto_id)
            ->first();
    
        if (!$ludo_challenge) {
            return response()->json([
                "status" => "false",
                "message" => "Ludo challenge not found"
            ]);
        }
    
        $game_id = $ludo_challenge->game_id;
    
        // Prepare message
        $heading_msg = $title;
        $content_msg = $sender_name . ": " . $message;
    
        // Send only if push notifications enabled
        if ($to_data->push_noti == 1) {
            $send = $this->send_onesignal_noti_chat(
                $heading_msg,
                $content_msg,
                $token,
                $to_member_id,
                $game_id
            );
    
            if ($send) {
                return response()->json([
                    "status"  => "true",
                    "title"   => $title,
                    "message" => $content_msg,
                    "token"   => $token,
                    "sender"  => $sender_name
                ]);
            }
        }
    
        return response()->json([
            "status"  => "false",
            "title"   => $title,
            "message" => "Notification not sent",
            "token"   => $token,
            "sender"  => $sender_name
        ]);
    }

	
	public function liveChallengeList($game_id) {
        $member_id = Auth::user()->member_id;
        
        $data['challenge_list'] = DB::table('ludo_challenge as l')
                ->leftJoin('member as m', 'm.member_id', '=', 'l.member_id')
                ->leftjoin("challenge_result_upload as cr1",function($join){
                    $join->on('cr1.member_id', '=', 'l.member_id')
                        ->on('cr1.ludo_challenge_id', '=', 'l.ludo_challenge_id');
                })
                ->leftjoin("challenge_result_upload as cr2",function($join1){
                    $join1->on('cr2.member_id', '=', 'l.accepted_member_id')
                        ->on('cr2.ludo_challenge_id', '=', 'l.ludo_challenge_id');
                })
                ->select('l.*','m.first_name','m.last_name', DB::raw('(CASE 
                WHEN profile_image = "" THEN ""
                ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", profile_image) 
                END) AS profile_image'),\DB::raw("(SELECT CONCAT(first_name,' ',last_name) FROM member
                          WHERE member.member_id = l.accepted_member_id
                        ) as accepted_member_name"),
                        \DB::raw("(SELECT ". DB::raw('(CASE 
                        WHEN profile_image = "" THEN ""
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", profile_image) 
                        END) AS profile_image') ." FROM member
                          WHERE member.member_id = l.accepted_member_id
                        ) as accepted_profile_image"),'cr1.result_status as added_result','cr2.result_status as accepted_result','m.player_id',\DB::raw("(SELECT player_id FROM member
                          WHERE member.member_id = l.accepted_member_id
                        ) as accepted_player_id")
                        )
                ->where('challenge_status','1')
                ->where('accept_status','0')
				->where('game_id',$game_id)
                ->where("l.member_id","!=", $member_id)
                ->Where("l.accepted_member_id","!=", $member_id)
                ->orderBy('date_created', 'DESC')
                ->get();
        
        $member = DB::table('member')
                ->where("member_id", $member_id)
                ->select("ludo_username")
                ->first();
        
        if($member->ludo_username != ''){
            $ludo_username = unserialize($member->ludo_username);
        } else {
            $ludo_username = $member->ludo_username;
        }        
		
        $data['ludo_game_name'] = '';
        if (is_array($ludo_username) && array_key_exists($game_id, $ludo_username)) {
            $data['ludo_game_name'] = $ludo_username[$game_id];
        }
		
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public function myChallengeList($game_id) {
        $member_id = Auth::user()->member_id;
        
        $where_in = ['1','4'];
        
        $data['challenge_list'] = DB::table('ludo_challenge as l')
                ->leftJoin('member as m', 'm.member_id', '=', 'l.member_id')
                ->leftjoin("challenge_result_upload as cr1",function($join){
                    $join->on('cr1.member_id', '=', 'l.member_id')
                        ->on('cr1.ludo_challenge_id', '=', 'l.ludo_challenge_id');
                })
                ->leftjoin("challenge_result_upload as cr2",function($join1){
                    $join1->on('cr2.member_id', '=', 'l.accepted_member_id')
                        ->on('cr2.ludo_challenge_id', '=', 'l.ludo_challenge_id');
                })
                ->select('l.*','m.first_name','m.last_name', DB::raw('(CASE 
                WHEN profile_image = "" THEN ""
                ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", profile_image) 
                END) AS profile_image'),\DB::raw("(SELECT CONCAT(first_name,' ',last_name) FROM member
                          WHERE member.member_id = l.accepted_member_id
                        ) as accepted_member_name"),
                        \DB::raw("(SELECT ". DB::raw('(CASE 
                        WHEN profile_image = "" THEN ""
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", profile_image) 
                        END) AS profile_image') ." FROM member
                          WHERE member.member_id = l.accepted_member_id
                        ) as accepted_profile_image"),'cr1.result_status as added_result','cr2.result_status as accepted_result','m.player_id',\DB::raw("(SELECT player_id FROM member
                          WHERE member.member_id = l.accepted_member_id
                        ) as accepted_player_id")
                        )
                ->whereIn('challenge_status',$where_in)
                ->Where(function ($query) use ($member_id){
                    $query->where("l.member_id", $member_id)
                      ->orWhere("l.accepted_member_id",$member_id);
                })
				->where('game_id',$game_id)
                ->orderBy('date_created', 'DESC')
                ->get();
        
        $member = DB::table('member')
                ->where("member_id", $member_id)
                ->select("ludo_username")
                ->first();
        
        if($member->ludo_username != ''){
            $ludo_username = unserialize($member->ludo_username);
        } else {
            $ludo_username = $member->ludo_username;
        }		
		
        $data['ludo_game_name'] = '';
        if (is_array($ludo_username) && array_key_exists($game_id, $ludo_username)) {
            $data['ludo_game_name'] = $ludo_username[$game_id];
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
	
	public function challengeResultList($game_id) {
        $member_id = Auth::user()->member_id;
        
        $where_in = ['2','3'];
        $data['challenge_list'] = DB::table('ludo_challenge as l')
                ->leftJoin('member as m', 'm.member_id', '=', 'l.member_id')
                ->leftjoin("challenge_result_upload as cr1",function($join){
                    $join->on('cr1.member_id', '=', 'l.member_id')
                        ->on('cr1.ludo_challenge_id', '=', 'l.ludo_challenge_id');
                })
                ->leftjoin("challenge_result_upload as cr2",function($join1){
                    $join1->on('cr2.member_id', '=', 'l.accepted_member_id')
                        ->on('cr2.ludo_challenge_id', '=', 'l.ludo_challenge_id');
                })
                ->select('l.*','m.first_name','m.last_name', DB::raw('(CASE 
                WHEN profile_image = "" THEN ""
                ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", profile_image) 
                END) AS profile_image'),\DB::raw("(SELECT CONCAT(first_name,' ',last_name) FROM member
                          WHERE member.member_id = l.accepted_member_id
                        ) as accepted_member_name"),
                        \DB::raw("(SELECT ". DB::raw('(CASE 
                        WHEN profile_image = "" THEN ""
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", profile_image) 
                        END) AS profile_image') ." FROM member
                          WHERE member.member_id = l.accepted_member_id
                        ) as accepted_profile_image"),'cr1.result_status as added_result','cr2.result_status as accepted_result','m.player_id',\DB::raw("(SELECT player_id FROM member
                          WHERE member.member_id = l.accepted_member_id
                        ) as accepted_player_id")
                        )
                ->whereIn('challenge_status',$where_in)
                ->Where(function ($query) use ($member_id){
                    $query->where("l.member_id", $member_id)
                      ->orWhere("l.accepted_member_id",$member_id);
                })
				->where('game_id',$game_id)
                ->orderBy('date_created', 'DESC')
                ->get();
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
	
	function generate_ludo_auto_id($ludo_pre) {
        $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $r_str = '';
        for ($i = 0; $i < 8; $i++) {
            $r_str .= substr($chars, rand(0, strlen($chars)), 1);
        }
        $new_user_name = $ludo_pre . $r_str;
        $data = DB::table('ludo_challenge')
                ->where('auto_id', $new_user_name)
                ->first();
        if ($data) {
            $this->generate_ludo_auto_id($ludo_pre);
        } else {
            return $new_user_name;
        }
    }
    
    public function addChallenge(Request $request) {       
        
        if (($request->input('submit')) && $request->input('submit') == 'addChallenge') {

            $validator = Validator::make($request->all(), [
                        'member_id' => 'required',
                        'game_id' => 'required',
                        'ludo_king_username' => 'required',
                        'with_password' => 'required',
                        'coin' => 'required|numeric|min:10|max:100000',
                            ], [
                        'member_id.required' => trans('message.err_member_id'),
                        'game_id.required' => trans('message.err_game_id'),
                        'ludo_king_username.required' => trans('message.err_ludo_username'),
                        'with_password.required' => trans('message.err_with_password'),
                        'coin.required' => trans('message.err_coin_req'),
                        'coin.min' => trans('message.err_coin_min'),
                        'coin.max' => trans('message.err_coin_max'),
            ]);
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            }

            
            if ($request->input('coin') % 10 != 0) {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_coin_multiply');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if ($request->input('with_password') && $request->input('challenge_password') == '') {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_password_req');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }

           $member_data = DB::table('member')
                    ->where('member_id', $request->input('member_id'))
                    ->first();
                    
            if ($member_data->wallet_balance + $member_data->join_money >= $request->input('coin')) {
                
                if($request->input('coin') > 100) {
                    $winning_price = ($request->input('coin') * 2) - ((($request->input('coin') * 2) * $this->system_config['coin_up_to_hundrade']) / 100);
                } else {
                    $winning_price = ($request->input('coin') * 2) - ((($request->input('coin') * 2) * $this->system_config['coin_under_hundrade']) / 100);
                }

                // $auto_id = $this->generate_ludo_auto_id('LGTC_');
                
                    $ludo_challenge_data = [
                                            'member_id' => $request->input('member_id'),
                                            'ludo_king_username' => $request->input('ludo_king_username'),
                                            'with_password' => $request->input('with_password'),
                                            'challenge_password' => $request->input('challenge_password'),
                                            'coin' => $request->input('coin'),
                                            'winning_price' => $winning_price,
                                            'game_id' => $request->input('game_id'),                                            
                                            // 'auto_id' => $auto_id
                                            'date_created' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                                        ];
                    
                    $id =DB::table('ludo_challenge')->insertGetId($ludo_challenge_data);
                    
					$game_data = DB::table('game')
                    ->where('game_id', $request->input('game_id'))
                    ->first();
				
                    $auto_id = $game_data->id_prefix . '_' . $id;
                    
                    $ludo_challenge_update_data = [
                                            'auto_id' => $auto_id
                                        ];
                                        
                    DB::table('ludo_challenge')->where('ludo_challenge_id', $id)->update($ludo_challenge_update_data);
                                        
                        if ($member_data->join_money > $request->input('coin')) {
                            $join_money = $member_data->join_money - $request->input('coin');
                            $wallet_balance = $member_data->wallet_balance;
                        } elseif ($member_data->join_money < $request->input('coin')) {
                            $join_money = 0;
                            $amount1 = $request->input('coin') - $member_data->join_money;
                            $wallet_balance = $member_data->wallet_balance - $amount1;
                        } elseif ($member_data->join_money == $request->input('coin')) {
                            $join_money = 0;
                            $wallet_balance = $member_data->wallet_balance;
                        }
                        
                        if($member_data->ludo_username != ''){
                            $ludo_username = unserialize($member_data->ludo_username);
                        } else {
                            $ludo_username = $member_data->ludo_username;
                        }

                            if (is_array($ludo_username)) {
                                if (array_key_exists($request->input('game_id'), $ludo_username)) {
                                    $ludo_username[$request->input('game_id')] = $request->input('ludo_king_username');
                                } else {
                                    $ludo_username[$request->input('game_id')] = $request->input('ludo_king_username');
                                }

                                $ludo_username = serialize($ludo_username);                                
                            } else {
                                $ludo_username = array(
                                    $request->input('game_id') => $request->input('ludo_king_username'),
                                );

                                $ludo_username = serialize($ludo_username);                                
                            }

                        $member_update_data = [
                            'ludo_username' => $ludo_username,
                            'join_money' => $join_money,
                            'wallet_balance' => $wallet_balance,
                        ];
                        
                        DB::table('member')->where('member_id', $request->input('member_id'))->update($member_update_data);
                        
                        $browser = '';
                        $agent = new Agent();
                        if ($agent->isMobile()) {
                            $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                        } elseif ($agent->isDesktop()) {
                            $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                        } elseif ($agent->isRobot()) {
                            $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                        }
                        
                        $ip = $this->getIp();
                        $acc_data = [
                            'member_id' => $request->input('member_id'),
                            'pubg_id' => $member_data->pubg_id,
                            'deposit' => 0,
                            'withdraw' => $request->input('coin'),
                            'join_money' => $join_money,
                            'win_money' => $wallet_balance,
                            'note' => 'Add '. $game_data->game_name .' Challenge #' . $id,
                            'note_id' => '14',
                            'entry_from' => '1',
                            'ip_detail' => $ip,
                            'browser' => $browser,
                            'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
                        ];
                        DB::table('accountstatement')->insert($acc_data);                                                

                        $follower = json_decode($game_data->follower,true);
                        
                        $follower_data = DB::table('member')
                                        ->select('member_id','player_id')
                                        ->where('member_status', '1')
                                        ->where('push_noti', '1')
                                        ->where('player_id','!=', '')
                                        ->whereIn('member_id', $follower)
                                        ->where('member_id','!=', $request->input('member_id'))
                                        ->get();

                        $player_ids = array();
                        $member_ids = array();

                        foreach($follower_data as $mem){                                            
                            array_push($player_ids,$mem->player_id);                                
                            array_push($member_ids,$mem->member_id);                            
                        }

                        if(!empty($player_ids)){

                            $heading_msg = 'New Challenge Available';
                            $content_msg = 'New Challenge "' . $auto_id . '" available in '. $game_data->game_name . '. If you interested then accept the challenge.';                                                                            
                                                
                            $this->send_onesignal_noti($heading_msg,$content_msg,$player_ids,$member_ids,$request->input('game_id'),true);
                        }

                        $array['status'] = 'true';
                        $array['title'] = 'Success!';
                        $array['message'] = trans('message.succ_challenge_added');
                        echo json_encode($array,JSON_UNESCAPED_UNICODE);
                        exit;
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = trans('message.err_balance_low');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    }
    
    public function acceptChallenge(Request $request) {
        if ($request->input('submit') && $request->input('submit') == 'acceptChallenge') {
    
            // ----------------- Validation -----------------
            $validator = Validator::make($request->all(), [
                'ludo_challenge_id' => 'required',
                'accepted_member_id' => 'required',
                'ludo_king_username' => 'required',
                'coin' => 'required|numeric|min:10|max:10000',
            ], [
                'ludo_challenge_id.required' => trans('message.err_ludo_challenge_id'),
                'accepted_member_id.required' => trans('message.err_member_id'),
                'ludo_king_username.required' => trans('message.err_ludo_username'),
                'coin.required' => trans('message.err_coin_req'),
                'coin.min' => trans('message.err_coin_min'),
                'coin.max' => trans('message.err_coin_max'),
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'title' => 'Error!',
                    'message' => $validator->errors()->first()
                ], 422);
            }
    
            // ----------------- Coin multiple check -----------------
            if ($request->input('coin') % 10 != 0) {
                return response()->json([
                    'status' => false,
                    'title' => 'Error!',
                    'message' => trans('message.err_coin_multiply')
                ], 422);
            }
    
            // ----------------- Challenge check -----------------
            $ludo_challenge_data = DB::table('ludo_challenge')
                ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                ->first();
    
            if (!$ludo_challenge_data) {
                return response()->json([
                    'status' => false,
                    'title' => 'Error!',
                    'message' => 'Challenge not found'
                ], 404);
            }
    
            if ($ludo_challenge_data->accept_status == '1') {
                return response()->json([
                    'status' => false,
                    'title' => 'Error!',
                    'message' => 'Match Already Accepted !'
                ]);
            }
    
            if ($ludo_challenge_data->with_password) {
                if ($request->input('challenge_password') == '') {
                    return response()->json([
                        'status' => false,
                        'title' => 'Error!',
                        'message' => trans('message.err_password_req')
                    ]);
                } elseif ($request->input('challenge_password') != $ludo_challenge_data->challenge_password) {
                    return response()->json([
                        'status' => false,
                        'title' => 'Error!',
                        'message' => trans('message.text_err_pass_incorrect')
                    ]);
                }
            }
    
            // ----------------- Member check -----------------
            $member_data = DB::table('member')
                ->where('member_id', $request->input('accepted_member_id'))
                ->first();
    
            if (!$member_data) {
                return response()->json([
                    'status' => false,
                    'title' => 'Error!',
                    'message' => 'Member not found'
                ], 404);
            }
    
            // ----------------- Balance check -----------------
            if ($member_data->wallet_balance + $member_data->join_money < $request->input('coin')) {
                return response()->json([
                    'status' => false,
                    'title' => 'Error!',
                    'message' => trans('message.err_balance_low')
                ]);
            }
    
            // ----------------- Update challenge -----------------
            $update_ludo_challenge_data = [
                'accepted_member_id' => $request->input('accepted_member_id'),
                'accepted_ludo_king_username' => $request->input('ludo_king_username'),
                'accept_status' => '1',
                'accepted_date' => Carbon::now($this->timezone)->format('Y-m-d H:i:s'),
            ];
    
            DB::table('ludo_challenge')
                ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                ->update($update_ludo_challenge_data);
    
            // ----------------- Wallet update -----------------
            if ($member_data->join_money > $request->input('coin')) {
                $join_money = $member_data->join_money - $request->input('coin');
                $wallet_balance = $member_data->wallet_balance;
            } elseif ($member_data->join_money < $request->input('coin')) {
                $join_money = 0;
                $amount1 = $request->input('coin') - $member_data->join_money;
                $wallet_balance = $member_data->wallet_balance - $amount1;
            } else {
                $join_money = 0;
                $wallet_balance = $member_data->wallet_balance;
            }
    
            // ----------------- Ludo username update -----------------
            if ($member_data->ludo_username != '') {
                $ludo_username = unserialize($member_data->ludo_username);
            } else {
                $ludo_username = $member_data->ludo_username;
            }
    
            if (is_array($ludo_username)) {
                $ludo_username[$ludo_challenge_data->game_id] = $request->input('ludo_king_username');
                $ludo_username = serialize($ludo_username);
            } else {
                $ludo_username = serialize([
                    $ludo_challenge_data->game_id => $request->input('ludo_king_username')
                ]);
            }
    
            DB::table('member')
                ->where('member_id', $request->input('accepted_member_id'))
                ->update([
                    'ludo_username' => $ludo_username,
                    'join_money' => $join_money,
                    'wallet_balance' => $wallet_balance,
                ]);
    
            // ----------------- Account statement -----------------
            $game_data = DB::table('game')->where('game_id', $ludo_challenge_data->game_id)->first();
            $ip = $this->getIp();
    
            DB::table('accountstatement')->insert([
                'member_id' => $request->input('accepted_member_id'),
                'pubg_id' => $member_data->pubg_id,
                'deposit' => 0,
                'withdraw' => $request->input('coin'),
                'join_money' => $join_money,
                'win_money' => $wallet_balance,
                'note' => 'Accept ' . $game_data->game_name . ' Challenge #' . $request->input('ludo_challenge_id'),
                'note_id' => '15',
                'entry_from' => '1',
                'ip_detail' => $ip,
                'browser' => $this->getBrowserInfo($request),
                'accountstatement_dateCreated' => Carbon::now($this->timezone)->format('Y-m-d H:i:s'),
            ]);
    
            // ----------------- Opponent member check -----------------
            $not_member_data = DB::table('member')
                ->where('member_id', $ludo_challenge_data->member_id)
                ->first();
    
            if ($not_member_data) {
                // update both buddy lists safely
                $this->updateBuddyList($not_member_data, $member_data, $ludo_challenge_data->game_id);
                $this->updateBuddyList($member_data, $not_member_data, $ludo_challenge_data->game_id);
    
                // send notification
                $heading_msg = 'Challenge Accepted';
                $content_msg = $request->input('ludo_king_username') . " has accepted your " . $ludo_challenge_data->auto_id . " Challenge.";
    
                if ($not_member_data->push_noti == '1') {
                    $this->send_onesignal_noti($heading_msg, $content_msg, $not_member_data->player_id, $not_member_data->member_id, $ludo_challenge_data->game_id);
                }
            }
    
            // ----------------- Success response -----------------
            return response()->json([
                'status' => true,
                'title' => 'Success!',
                'message' => trans('message.succ_challenge_accepted')
            ]);
        }
    }
    private function updateBuddyList($member, $buddy, $game_id)
    {
        $budy_list = [];
    
        if (!empty($member->budy_list)) {
            $budy_list = unserialize($member->budy_list);
        }
    
        if (!is_array($budy_list)) {
            $budy_list = [];
        }
    
        $budy_list[$game_id][] = $buddy->member_id;
        $budy_list[$game_id] = array_unique($budy_list[$game_id]); // Avoid duplicates
    
        DB::table('member')
            ->where('member_id', $member->member_id)
            ->update([
                'budy_list' => serialize($budy_list),
            ]);
    }



    public function cancelChallenge(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'cancelChallenge') {

            $validator = Validator::make($request->all(), [
                        'ludo_challenge_id' => 'required',
                        'member_id' => 'required',
                            ], [
                        'ludo_challenge_id.required' => trans('message.err_ludo_challenge_id'),
                        'member_id.required' => trans('message.err_member_id'),
                    ]);
                    
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            }
           
           $ludo_challenge_data = DB::table('ludo_challenge')
                    ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                    ->first();
            
            $game_data = DB::table('game')
                        ->where('game_id', $ludo_challenge_data->game_id)
                        ->first();
            // 🔒 Prevent cancel if someone has accepted this challenge
            if ($ludo_challenge_data->accept_status == 1) {
                $array['status'] = 'false';
                $array['title'] = 'Error!';
                $array['message'] = 'Someone has already accepted this challenge. You cannot cancel this challenge.';
                echo json_encode($array, JSON_UNESCAPED_UNICODE); exit;
            }
            
            if($ludo_challenge_data->challenge_status == '2') {                
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = trans('message.err_already_cancel');echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            }   
            
                    $member_data = DB::table('member')
                    ->where('member_id', $request->input('member_id'))
                    ->first();
            
                    $ludo_challenge_update_data = [
                                            'challenge_status' => '2',
                                            'canceled_by' => $request->input('member_id'),
                                        ];
                                        
                    DB::table('ludo_challenge')->where('ludo_challenge_id', $request->input('ludo_challenge_id'))->update($ludo_challenge_update_data);

                        $join_money = $member_data->join_money + $ludo_challenge_data->coin;
                        
                        $member_update_data = [
                            'join_money' => $join_money,
                        ];
                        
                        DB::table('member')->where('member_id', $request->input('member_id'))->update($member_update_data);
                        
                        $browser = '';
                        $agent = new Agent();
                        if ($agent->isMobile()) {
                            $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                        } elseif ($agent->isDesktop()) {
                            $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                        } elseif ($agent->isRobot()) {
                            $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                        }
                        
                        $ip = $this->getIp();
                        $acc_data = [
                            'member_id' => $request->input('member_id'),
                            'pubg_id' => $member_data->pubg_id,
                            'deposit' => $ludo_challenge_data->coin,
                            'withdraw' => 0,
                            'join_money' => $join_money,
                            'win_money' => $member_data->wallet_balance,
                            'note' => 'Cancel '. $game_data->game_name.' Challenge #' . $request->input('ludo_challenge_id'),
                            'note_id' => '16',
                            'entry_from' => '1',
                            'ip_detail' => $ip,
                            'browser' => $browser,
                            'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s'),
                        ];
                        DB::table('accountstatement')->insert($acc_data);
                            
                        if($ludo_challenge_data->accept_status == 1 && $request->input('canceled_by_flag') == 0){
                            
                            $other_member_data = DB::table('member')
                            ->where('member_id', $ludo_challenge_data->accepted_member_id)
                            ->first();
                            
                            $other_join_money = $other_member_data->join_money + $ludo_challenge_data->coin;
                                
                                $accepted_member_update_data = [
                                    'join_money' => $other_join_money,
                                ];
                                
                                DB::table('member')->where('member_id', $ludo_challenge_data->accepted_member_id)->update($accepted_member_update_data);
                                
                                $browser = '';
                                $agent = new Agent();
                                if ($agent->isMobile()) {
                                    $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                                } elseif ($agent->isDesktop()) {
                                    $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                                } elseif ($agent->isRobot()) {
                                    $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                                }
                                
                                $ip = $this->getIp();
                                $accepted_acc_data = [
                                    'member_id' => $ludo_challenge_data->accepted_member_id,
                                    'pubg_id' => $other_member_data->pubg_id,
                                    'deposit' => $ludo_challenge_data->coin,
                                    'withdraw' => 0,
                                    'join_money' => $other_join_money,
                                    'win_money' => $other_member_data->wallet_balance,
                                    'note' => 'Cancel '. $game_data->game_name.' Challenge #' . $request->input('ludo_challenge_id'),
                                    'note_id' => '16',
                                    'entry_from' => '1',
                                    'ip_detail' => $ip,
                                    'browser' => $browser,
                                    'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s'),
                                ];
                                DB::table('accountstatement')->insert($accepted_acc_data);
                                
                        }
                        
                        if($request->input('canceled_by_flag') == 1){
                            
                            $other_member_data = DB::table('member')
                            ->where('member_id', $ludo_challenge_data->member_id)
                            ->first();
                            
                            $other_join_money = $other_member_data->join_money + $ludo_challenge_data->coin;
                                
                                $accepted_member_update_data = [
                                    'join_money' => $other_join_money,
                                ];
                                
                                DB::table('member')->where('member_id', $ludo_challenge_data->member_id)->update($accepted_member_update_data);
                                
                                $browser = '';
                                $agent = new Agent();
                                if ($agent->isMobile()) {
                                    $browser = $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device());
                                } elseif ($agent->isDesktop()) {
                                    $browser = $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser());
                                } elseif ($agent->isRobot()) {
                                    $browser = $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot());
                                }
                                
                                $ip = $this->getIp();
                                $accepted_acc_data = [
                                    'member_id' => $ludo_challenge_data->member_id,
                                    'pubg_id' => $other_member_data->pubg_id,
                                    'deposit' => $ludo_challenge_data->coin,
                                    'withdraw' => 0,
                                    'join_money' => $other_join_money,
                                    'win_money' => $other_member_data->wallet_balance,
                                    'note' => 'Cancel '. $game_data->game_name.' Challenge #' . $request->input('ludo_challenge_id'),
                                    'note_id' => '16',
                                    'entry_from' => '1',
                                    'ip_detail' => $ip,
                                    'browser' => $browser,
                                    'accountstatement_dateCreated' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s'),
                                ];
                                DB::table('accountstatement')->insert($accepted_acc_data);
                                
                        }
                        
                        if(isset($other_member_data)) {
                            
                            $heading_msg = 'Contest Canceled';
                            $content_msg = $ludo_challenge_data->auto_id ." is canceled by " . $member_data->first_name . " ". $member_data->last_name .".";                                                                                     
                            
                            if($other_member_data->push_noti == '1' || $other_member_data->push_noti == 1) {
                                $this->send_onesignal_noti($heading_msg,$content_msg,$other_member_data->player_id,$other_member_data->member_id,$ludo_challenge_data->game_id);
                            }

                            $array['status'] = 'true';
                            $array['title'] = 'Success!';
                            $array['message'] = trans('message.succ_cancel_challenge');
                            echo json_encode($array,JSON_UNESCAPED_UNICODE);
                            exit;
                        
                        } else {
                            $array['status'] = 'true';
                            $array['title'] = 'Success!';
                            $array['message'] = trans('message.succ_cancel_challenge');
                            echo json_encode($array,JSON_UNESCAPED_UNICODE);
                            exit;
                        }
        }
    }

    public function updataChallengeRoom(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'updateRoom') {

            $validator = Validator::make($request->all(), [
                        'ludo_challenge_id' => 'required',
                        'room_code' => 'required',
                            ], [
                        'ludo_challenge_id.required' => trans('message.err_ludo_challenge_id'),
                        'room_code.required' => trans('message.err_room_code'),
                    ]);
            if ($validator->fails()) {
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            }
            
            $is_result_uploaded = DB::table('challenge_result_upload')
                    ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                    ->first();
            
            if($is_result_uploaded != ''){                
                $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = trans('message.err_cant_update_room_code');echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
            }
            
            $ludo_challenge_data = DB::table('ludo_challenge')
                    ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                    ->first();
            
           $member_data = DB::table('member')
                    ->where('member_id', $ludo_challenge_data->accepted_member_id)
                    ->first();
                    
                
                    $update_ludo_challenge_data = [
                                            'room_code' => $request->input('room_code'),
                                        ];
                                        
                    DB::table('ludo_challenge')->where('ludo_challenge_id', $request->input('ludo_challenge_id'))->update($update_ludo_challenge_data);
                    
                    $challenge_room_code_data = [
                                            'challenge_id' => $request->input('ludo_challenge_id'),
                                            'room_code' => $request->input('room_code'),                                            
                                            'date_created' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s'),
                                        ];
                    
                    DB::table('challenge_room_code')->insertGetId($challenge_room_code_data);
                    
                    $heading_msg = 'Challenge Room Code Updated';
                    $content_msg = "Room Code of " . $ludo_challenge_data->auto_id ." is " . $request->input('room_code') . ".Join your room.";                                        
                                           
                    if($member_data->push_noti == '1' || $member_data->push_noti == 1) {
                        $this->send_onesignal_noti($heading_msg,$content_msg,$member_data->player_id,$member_data->member_id,$ludo_challenge_data->game_id);
                    }

                    $array['status'] = 'true';
                    $array['title'] = 'Success!';
                    $array['message'] = trans('message.succ_room_updated');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);
                    exit;
                    
        }
    }
    
    public function challengeResultUpload(Request $request) {
        if (($request->input('submit')) && $request->input('submit') == 'uploadResult') {
    
            // ✅ Validate request
            if ($request->input('result_status') == '2') {
                $validator = Validator::make($request->all(), [
                    'reason' => 'required',
                    'ludo_challenge_id' => 'required',
                    'member_id' => 'required',
                ]);
            } else {
                $validator = Validator::make($request->all(), [
                    'ludo_challenge_id' => 'required',
                    'member_id' => 'required',
                ]);
            }
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => $validator->errors()->first()
                ]);
            }
    
            // ✅ Check if same user already submitted
            $same_user_result_exist = DB::table('challenge_result_upload')
                ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                ->where('member_id', $request->input('member_id'))
                ->first();
    
            if ($same_user_result_exist) {
                return response()->json([
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => trans('message.err_result_already')
                ]);
            }
    
            // ✅ Challenge + game info
            $ludo_challenge_data = DB::table('ludo_challenge')
                ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                ->first();
    
            $game_data = DB::table('game')
                ->where('game_id', $ludo_challenge_data->game_id)
                ->first();
    
            if ($ludo_challenge_data->challenge_status == '2' || $ludo_challenge_data->challenge_status == '3') {
                return response()->json([
                    'status' => 'false',
                    'title' => 'Error!',
                    'message' => trans('message.err_result_decided')
                ]);
            }
            
            
    
            // ✅ Identify current user and opponent
            $current_member = DB::table('member')
                ->where('member_id', $request->input('member_id'))
                ->first();
    
            $opponent_id = ($ludo_challenge_data->member_id == $request->input('member_id'))
                ? $ludo_challenge_data->accepted_member_id
                : $ludo_challenge_data->member_id;
    
            $opponent_member = DB::table('member')
                ->where('member_id', $opponent_id)
                ->first();
    
            // ✅ Process based on result_status
            $result_status = $request->input('result_status');
            $exist_result = DB::table('challenge_result_upload')
                ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                ->first();
    
            // ---------- WIN ----------
            if ($result_status == '0') {
                if (!$request->input('result_image')) {
                    return response()->json([
                        'status' => 'false',
                        'title' => 'Error!',
                        'message' => trans('message.err_req_result_image')
                    ]);
                }
    
                DB::table('challenge_result_upload')->insert([
                    'member_id' => $current_member->member_id,
                    'ludo_challenge_id' => $request->input('ludo_challenge_id'),
                    'result_uploded_by_flag' => $request->input('result_uploded_by_flag'),
                    'result_image' => $request->input('result_image'),
                    'result_status' => '0',
                    'date_created' => Carbon::now($this->timezone)->format('Y-m-d H:i:s'),
                ]);
    
                if ($exist_result && $exist_result->result_status == '1') {
                    // Opponent submitted LOSS → current is winner
                    DB::table('ludo_challenge')->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                        ->update(['winner_id' => $current_member->member_id, 'challenge_status' => '3']);
                } elseif ($exist_result && ($exist_result->result_status == '0' || $exist_result->result_status == '2')) {
                    // Conflict → admin review
                    DB::table('ludo_challenge')->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                        ->update(['challenge_status' => '4']);
                }
    
                $heading_msg = 'Win Request Generated';
                $content_msg = "Your Opponent submitted Win Request for challenge #" . $ludo_challenge_data->auto_id;
    
                if ($opponent_member->push_noti == 1) {
                    $this->send_onesignal_noti($heading_msg, $content_msg, $opponent_member->player_id, $opponent_member->member_id, $ludo_challenge_data->game_id);
                }
            }
    
            // ---------- LOSS ----------
            
            if ($result_status == '1') {
                DB::table('challenge_result_upload')->insert([
                    'member_id' => $current_member->member_id,
                    'ludo_challenge_id' => $request->input('ludo_challenge_id'),
                    'result_uploded_by_flag' => $request->input('result_uploded_by_flag'),
                    'result_status' => '1',
                    'date_created' => Carbon::now($this->timezone)->format('Y-m-d H:i:s'),
                ]);
            
                if ($exist_result && $exist_result->result_status == '2') {
                    // Conflict with error → admin review
                    DB::table('ludo_challenge')
                        ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                        ->update(['challenge_status' => '4']);
                } else {
                    // Opponent wins
                    DB::table('ludo_challenge')
                        ->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                        ->update(['winner_id' => $opponent_member->member_id, 'challenge_status' => '3']);
            
                    $wallet_balance = $opponent_member->wallet_balance + $ludo_challenge_data->winning_price;
                    DB::table('member')->where('member_id', $opponent_member->member_id)->update(['wallet_balance' => $wallet_balance]);
            
                    DB::table('accountstatement')->insert([
                        'member_id' => $opponent_member->member_id,
                        'pubg_id' => $opponent_member->pubg_id,
                        'deposit' => $ludo_challenge_data->winning_price,
                        'withdraw' => 0,
                        'join_money' => $opponent_member->join_money,
                        'win_money' => $wallet_balance,
                        'note' => 'Win ' . $game_data->game_name . ' Challenge #' . $request->input('ludo_challenge_id'),
                        'note_id' => '17',
                        'entry_from' => '1',
                        'ip_detail' => $this->getIp(),
                        'browser' => $request->userAgent(), // ✅ fixed here too
                        'accountstatement_dateCreated' => Carbon::now($this->timezone)->format('Y-m-d H:i:s'),
                    ]);
                }
            
                // ✅ Notification to opponent (they are winner)
                if ($opponent_member->push_noti == 1) {
                    $this->send_onesignal_noti(
                        'You Won!',
                        "Opponent submitted LOSS for challenge #" . $ludo_challenge_data->auto_id,
                        $opponent_member->player_id,
                        $opponent_member->member_id,
                        $ludo_challenge_data->game_id
                    );
                }
            
                // ✅ Notification to current user (they lost)
                if ($current_member->push_noti == 1) {
                    $this->send_onesignal_noti(
                        'You Lost!',
                        "You submitted LOSS for challenge #" . $ludo_challenge_data->auto_id,
                        $current_member->player_id,
                        $current_member->member_id,
                        $ludo_challenge_data->game_id
                    );
                }
            }

    
            // ---------- ERROR ----------
            if ($result_status == '2') {
                DB::table('challenge_result_upload')->insert([
                    'member_id' => $current_member->member_id,
                    'ludo_challenge_id' => $request->input('ludo_challenge_id'),
                    'result_uploded_by_flag' => $request->input('result_uploded_by_flag'),
                    'reason' => $request->input('reason'),
                    'result_image' => $request->input('result_image'),
                    'result_status' => '2',
                    'date_created' => Carbon::now($this->timezone)->format('Y-m-d H:i:s'),
                ]);
    
                if ($exist_result && $exist_result->result_status == '2') {
                    // Cancel → refund both players
                    DB::table('ludo_challenge')->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                        ->update(['challenge_status' => '2']);
    
                    foreach ([$current_member, $opponent_member] as $player) {
                        $join_money = $player->join_money + $ludo_challenge_data->coin;
                        DB::table('member')->where('member_id', $player->member_id)->update(['join_money' => $join_money]);
    
                        DB::table('accountstatement')->insert([
                            'member_id' => $player->member_id,
                            'pubg_id' => $player->pubg_id,
                            'deposit' => $ludo_challenge_data->coin,
                            'withdraw' => 0,
                            'join_money' => $join_money,
                            'win_money' => $player->wallet_balance,
                            'note' => 'Cancel ' . $game_data->game_name . ' Challenge #' . $request->input('ludo_challenge_id'),
                            'note_id' => '16',
                            'entry_from' => '1',
                            'ip_detail' => $this->getIp(),
                            'browser' => request()->userAgent(),
                            'accountstatement_dateCreated' => Carbon::now($this->timezone)->format('Y-m-d H:i:s'),
                        ]);
                    }
                } elseif ($exist_result && $exist_result->result_status == '0') {
                    // Conflict with win → admin review
                    DB::table('ludo_challenge')->where('ludo_challenge_id', $request->input('ludo_challenge_id'))
                        ->update(['challenge_status' => '4']);
                }
    
                $heading_msg = 'Error Request Generated';
                $content_msg = "Your Opponent submitted Error Request for challenge #" . $ludo_challenge_data->auto_id;
    
                if ($opponent_member->push_noti == 1) {
                    $this->send_onesignal_noti($heading_msg, $content_msg, $opponent_member->player_id, $opponent_member->member_id, $ludo_challenge_data->game_id);
                }
            }
    
            return response()->json([
                'status' => 'true',
                'title' => 'Success!',
                'message' => trans('message.succ_result_uploaded')
            ]);
        }
    }

    
    public function followUnfollowGame(Request $request) {
                
        $validator = Validator::make($request->all(), [
                    'member_id' => 'required',
                    'game_id' => 'required',
                    'status' => 'required',
                    ], [
                    'member_id.required' => trans('message.err_member_id'),
                    'game_id.required' => trans('message.err_game_id'),
                    'status.required' => trans('message.err_status'),                        
                    ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;
        }
         
        $member_id = $request->input('member_id');
        $game_data = DB::table('game')
                ->where('game_id', $request->input('game_id'))
                ->first();
                                           
		$follower = json_decode($game_data->follower,true);

        $new_follower = array();
        if($request->input('status')) {

            if (in_array($member_id, $follower)) {
                $new_follower = $follower;
            } else {
                array_push($new_follower, $member_id);
            }

            $array['message'] = trans('message.succ_follow');
            
        } else {
            if (in_array($member_id, $follower)) {
                foreach ($follower as $row) {
                    if ($row !== $member_id) {
                        $new_follower[] = $row;
                    }
                }
            } else {
                $new_follower = $follower;
            }            

            $array['message'] = trans('message.succ_unfollow');
        }
        
        $new_follower = json_encode($new_follower);
        
		DB::table('game')->where('game_id', $request->input('game_id'))->update(array(
            'follower' => $new_follower
        ));                    
        
        $array['status'] = 'true';
        $array['title'] = 'Success!';
        echo json_encode($array,JSON_UNESCAPED_SLASHES);
        exit;              
    }

    public function getGameFollowStatus($game_id,$member_id) {
        
        $game_data = DB::table('game')
                ->where('game_id', $game_id)
                ->first();

		$follower = json_decode($game_data->follower,true);

        if (in_array($member_id, $follower)) {
            $array['is_follower'] = true;
        } else {
            $array['is_follower'] = false;
        }

        $array['status'] = 'true';
        $array['title'] = 'Success!';
        echo json_encode($array,JSON_UNESCAPED_SLASHES);
        exit;
    }

    // public function send_onesignal_noti($heading_msg,$content_msg,$player_id,$member_id,$game_id,$multi = false){
        
    //     if($this->system_config['one_signal_notification']){
    //         $msg = array(
    //                 'body'  => $content_msg,
    //                 'title' => $heading_msg,
    //                 // 'icon'  => 'myicon',/*Default Icon*/        
    //                 'icon'  => 'Default',                   
    //             );
            
    //         if($multi){
    //             $fields = array (
    //                 'registration_ids' => $player_id,
    //                 'notification' => $msg,        
    //                 );
    //         } else {
    //             $fields = array (
    //                 'to' => $player_id,
    //                 'notification' => $msg,        
    //                 );
    //         }        
                            
    //         $ch = curl_init();
    //         curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/fcm/send");
    //         curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Authorization:key=' . $this->system_config['app_id']));
    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    //         curl_setopt($ch, CURLOPT_HEADER, FALSE);
    //         curl_setopt($ch, CURLOPT_POST, TRUE);
    //         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    //         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            
    //         $not_response = curl_exec($ch);
            
    //         curl_close($ch);
            
    //         $not_response = json_decode($not_response,true);
                              
    //         if (isset($not_response['success'])) {
    //             if($multi){
    //                 foreach($member_id as $key => $val) {
    //                     $notification_data = [
    //                         'member_id' => $val,
    //                         'id' => $not_response['multicast_id'],
    //                         'heading' => $heading_msg,
    //                         'content' => $content_msg,
    //                         'game_id' => $game_id,
    //                         'date_created' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
    //                     ];
                        
    //                     DB::table('notifications')->insert($notification_data);
    //                 }
    //             } else {
    //                 $notification_data = [
    //                     'member_id' => $member_id,
    //                     'id' => $not_response['multicast_id'],
    //                     'heading' => $heading_msg,
    //                     'content' => $content_msg,
    //                     'game_id' => $game_id,
    //                     'date_created' => Carbon::createFromFormat('Y-m-d H:i:s',date('Y-m-d H:i:s'), 'UTC')->setTimezone($this->timezone)->format('Y-m-d H:i:s')
    //                 ];
                    
    //                 DB::table('notifications')->insert($notification_data);
    //             }
    //             return true;            
    //         } else {
    //             return true;
    //         }
    //     } else {
    //         return true;
    //     }
    // }
    
    public function send_onesignal_noti_chat(
        $heading_msg,
        $content_msg,
        $player_id,
        $member_id,
        $game_id,
        $multi = false
    ) {
        // Fetch OneSignal config from web_config table
        $onesignal_app_id = DB::table('web_config')
            ->where('web_config_name', 'onesignal_app_id')
            ->value('web_config_value');
    
        $onesignal_api_key = DB::table('web_config')
            ->where('web_config_name', 'onesignal_api_key')
            ->value('web_config_value');
    
        if ($onesignal_app_id && $onesignal_api_key) {
            // Prepare OneSignal payload
            $fields = [
                'app_id' => $onesignal_app_id,
                'include_player_ids' => $multi ? $player_id : [$player_id],
                'headings' => ["en" => $heading_msg],
                'contents' => ["en" => $content_msg],
                'data' => [
                    "type" => "chat",
                    "game_id" => $game_id,
                    "member_id" => $member_id
                ]
            ];
    
            // CURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Basic ' . $onesignal_api_key
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    
            $not_response = curl_exec($ch);
            curl_close($ch);
    
            $not_response = json_decode($not_response, true);
    
            // Debug ke liye log
            \Log::info("OneSignal Chat Response", $not_response);
    
            if (isset($not_response['id'])) {
                // Save notification in DB
                $notification_data = [
                    'member_id' => $multi ? json_encode($member_id) : $member_id,
                    'id' => $not_response['id'],
                    'heading' => $heading_msg,
                    'content' => $content_msg,
                    'game_id' => $game_id,
                    'date_created' => Carbon::now($this->timezone)->format('Y-m-d H:i:s')
                ];
                DB::table('notifications')->insert($notification_data);
                return true;
            } else {
                return false;
            }
        }
    
        return false;
    }

    
    public function send_onesignal_noti($heading_msg,$content_msg,$player_id,$member_id,$game_id,$multi = false){

        {
            // Fetch OneSignal config from web_config table
            $onesignal_app_id = DB::table('web_config')->where('web_config_name', 'onesignal_app_id')->value('web_config_value');
            $onesignal_api_key = DB::table('web_config')->where('web_config_name', 'onesignal_api_key')->value('web_config_value');
        
            if ($onesignal_app_id && $onesignal_api_key) {
        
                $fields = [
                    'app_id' => $onesignal_app_id, // OneSignal App ID
                    'include_player_ids' => $multi ? $player_id : [$player_id], // array of OneSignal player IDs
                    'headings' => ["en" => $heading_msg],
                    'contents' => ["en" => $content_msg],
                ];
        
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Basic ' . $onesignal_api_key // OneSignal REST API key
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        
                $not_response = curl_exec($ch);
                curl_close($ch);
        
                $not_response = json_decode($not_response, true);
        
                if (isset($not_response['id'])) {
                    // Save notification in DB
                    $notification_data = [
                        'member_id' => $multi ? json_encode($member_id) : $member_id,
                        'id' => $not_response['id'], // OneSignal notification ID
                        'heading' => $heading_msg,
                        'content' => $content_msg,
                        'game_id' => $game_id,
                        'date_created' => Carbon::now($this->timezone)->format('Y-m-d H:i:s')
                    ];
                    DB::table('notifications')->insert($notification_data);
                    return true;
                } else {
                    return false;
                }
            }
        
            return true;
        }
    }


    public function authenticate(Request $request) {
        $validator = Validator::make($request->all(), [
                    'user_name' => 'required',
                    'password' => 'required',
                        ], [
                    'user_name.required' => trans('message.err_username_req'),
                    'password.required' => trans('message.err_password_req'),
        ]);
        if ($validator->fails()) {
            $array['status'] = 'false';$array['title'] = 'Error!'; $array['message'] = $validator->errors()->first();echo json_encode($array,JSON_UNESCAPED_UNICODE);exit;            
            exit;
        }
        $user = DB::table('member')->where('user_name', $request->input('user_name'))->first();
        if ($user) {
            if (md5($request->input('password')) == $user->password) {
                if ($user->member_status == '1') {
                    if ($user->api_token == '') {
                        $api_token = uniqid() . base64_encode(str_random(40));
                        $member_data = [
                            'api_token' => $api_token];
                        DB::table('member')->where('member_id', $user->member_id)->update($member_data);
                        $user->api_token = $api_token;
                    }
                    
                        $player_id_data = [
                            'player_id' => $request->input('player_id')];
                        DB::table('member')->where('member_id', $user->member_id)->update($player_id_data);
                    
                    $user_data = DB::table('member')->where('member_id', $user->member_id)->first();

                    if ($user_data->mob_verify == 0) {
                        $array['status'] = 'false';
                        $array['title'] = 'Mobile Not Verified';
                        $array['message'] = 'Please verify your mobile number.';
                        $array['code'] = 'mob_not_verify';
                        $array['mob'] = $user_data->mobile_no;
                        echo json_encode($array, JSON_UNESCAPED_UNICODE);
                        exit;
                    }

                    // Hide sensitive data
                    unset($user_data->mob_verify_otp);

                    $array['status'] = 'true';
                    $array['title'] = trans('message.text_succ_login');
                    $array['message'] = $user_data;
                    $array['code'] = 'login_success';
                    echo json_encode($array, JSON_UNESCAPED_UNICODE);
                    exit;
                } else {
                    $array['status'] = 'false';
                    $array['title'] = 'Login failed!';
                    $array['message'] = trans('message.text_block_acc');
                    echo json_encode($array,JSON_UNESCAPED_UNICODE);    
                    exit;
                }
            } else {
                $array['status'] = 'false';
                $array['title'] = 'Login failed!';
                $array['message'] = trans('message.text_err_pass_incorrect');
                echo json_encode($array,JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $array['status'] = 'false';
            $array['title'] = 'Login failed!';
            $array['message'] = trans('message.text_err_username_incorrect');
            echo json_encode($array,JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    public function send_mob_verify_otp(Request $request)
    {
        // Validate mobile number input
        $validator = Validator::make($request->all(), [
            'mobile_no' => 'required|numeric|digits_between:7,10|exists:member,mobile_no',
        ], [
            'mobile_no.required'       => trans('message.err_mobile_no_req'),
            'mobile_no.numeric'        => trans('message.err_mobile_no_num'),
            'mobile_no.exists'         => trans('message.err_mobile_no_exist'),
            'mobile_no.digits_between' => trans('message.err_mobile_no_7to15'),
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'title'   => 'Error!',
                'message' => $validator->errors()->first()
            ]);
        }
    
        // Fetch member from DB
        $member = DB::table('member')
            ->where('mobile_no', $request->input('mobile_no'))
            ->first();
    
        // Check if member is active
        if ($member->member_status != 1) {
            return response()->json([
                'status'  => false,
                'title'   => 'Error!',
                'message' => trans('message.text_block_acc')
            ]);
        }
    
        // Generate 6-digit OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
        // Update OTP in member table
        DB::table('member')->where('member_id', $member->member_id)->update([
            'mob_verify_otp' => $otp
        ]);
    
        // Load config from .env
        $apiKey = env('SMS_API_KEY');
        $apiUrl = env('SMS_API_URL');
    
        // Send OTP using cURL
        $mobile_no = $request->input('mobile_no');
        $url = $apiUrl . '?' . http_build_query([
            'api_key' => $apiKey,
            'number'  => $mobile_no,
            'otp'     => $otp
        ]);
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
    
        // Check for cURL errors or success
        if ($curl_error) {
            return response()->json([
                'status'  => false,
                'title'   => 'Error!',
                'message' => 'SMS sending failed: ' . $curl_error
            ]);
        } else {
            return response()->json([
                'status'  => true,
                'title'   => 'Success!',
                'message' => 'OTP sent successfully',
                // 'otp'     => $otp // Remove this in production for security
            ]);
        }
    }
    public function verify_mobile_otp(Request $request)
    {
        $messages = [
            'mobile_no.required' => 'Mobile number is required.',
            'mobile_no.numeric' => 'Mobile number must be numeric.',
            'mobile_no.exists' => 'Mobile number does not exist.',
            'mobile_no.digits_between' => 'Mobile number must be between 10 digits.',
            'otp.required' => 'OTP is required.',
            'otp.digits' => 'Invalid OTP format.',
        ];

        $validator = Validator::make($request->all(), [
            'mobile_no' => 'required|numeric|digits_between:7,10|exists:member,mobile_no',
            'otp' => 'required|digits:6'
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'title' => 'Error!',
                'message' => $validator->errors()->first()
            ]);
        }

        $member = DB::table('member')
            ->where('mobile_no', $request->input('mobile_no'))
            ->where('mob_verify_otp', $request->input('otp'))
            ->first();

        if (!$member) {
            return response()->json([
                'status' => false,
                'title' => 'Error!',
                'message' => 'Mobile number or OTP is incorrect.'
            ]);
        }

        // ✅ Update mobile verified
        DB::table('member')->where('member_id', $member->member_id)->update([
            'mob_verify' => 1,
            'mob_verify_otp' => null
        ]);

        // ✅ Give referral bonus only once and only after mobile is verified
        if (
            $this->system_config['active_referral'] == '1' &&
            $member->referral_id > 0 &&
            $member->referral_bonus_given == 0
        ) {
            $wallet_balance = $this->system_config['referral'];

            // update balance and mark referral bonus given
            DB::table('member')->where('member_id', $member->member_id)->update([
                'join_money' => $wallet_balance,
                'referral_bonus_given' => 1
            ]);

            DB::table('referral')->insert([
                'member_id' => $member->member_id,
                'from_mem_id' => $member->referral_id,
                'referral_amount' => $wallet_balance,
                'referral_status' => '1',
                'entry_from' => '1'
            ]);

            // optional: insert into accountstatement
            $agent = new \Jenssegers\Agent\Agent();
            $browser = $agent->isMobile() ? $agent->platform() . ' ' . $agent->device() . ' ' . $agent->version($agent->device())
                    : ($agent->isDesktop() ? $agent->platform() . ' ' . $agent->browser() . ' ' . $agent->version($agent->browser())
                    : ($agent->isRobot() ? $agent->platform() . ' ' . $agent->robot() . ' ' . $agent->version($agent->robot()) : ''));

            $ip = $this->getIp();
            DB::table('accountstatement')->insert([
                'member_id' => $member->member_id,
                'from_mem_id' => $member->referral_id,
                'deposit' => $wallet_balance,
                'withdraw' => 0,
                'join_money' => $wallet_balance,
                'win_money' => 0,
                'note' => 'Register Referral',
                'note_id' => '3',
                'entry_from' => '1',
                'ip_detail' => $ip,
                'browser' => $browser,
                'accountstatement_dateCreated' => Carbon::now($this->timezone)->format('Y-m-d H:i:s')
            ]);
        }

        $user_data = DB::table('member')
            ->select('member_id', 'first_name', 'last_name', 'email_id', 'mobile_no', 'user_name', 'country_code', 'api_token', 'join_money', 'mob_verify')
            ->where('member_id', $member->member_id)
            ->first();

        return response()->json([
            'status' => true,
            'title' => 'Success!',
            'message' => 'Mobile number verified successfully.',
            // 'api_token' => $member->api_token,
            'user_data' => $user_data
        ]);
    }



/**
     * Submit a report with reporter's POV
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitReport(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'reporting_user_id' => 'required|integer|exists:member,member_id',
            'reported_user_id' => 'required|integer|exists:member,member_id|different:reporting_user_id',
            'match_id' => 'required|string|max:255',
            'reason' => 'required|string|max:5000',
            'reporter_pov_url' => 'required|url|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create the report
            $reportId = DB::table('match_reports')->insertGetId([
                'reporting_user_id' => $request->reporting_user_id,
                'reported_user_id' => $request->reported_user_id,
                'match_id' => $request->match_id,
                'reason' => $request->reason,
                'reporter_pov_url' => $request->reporter_pov_url,
                'reporter_pov_uploaded' => true,
                'status' => 'pending',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Report submitted successfully',
                'report_id' => $reportId
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Failed to submit report: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit report. Please try again later.'
            ], 500);
        }
    }

    /**
     * Check if user has any active reports against them
     * 
     * @param int $user_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkFlag($user_id)
    {
        try {
            // First, check database connection
            try {
                DB::connection()->getPdo();
            } catch (\Exception $e) {
                \Log::error('Database connection error: ' . $e->getMessage());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Database connection error',
                    'debug' => 'Could not connect to the database.'
                ], 500);
            }

            // Check if member table exists
            if (!Schema::hasTable('member')) {
                \Log::error('Member table does not exist');
                return response()->json([
                    'status' => 'error',
                    'message' => 'System configuration error',
                    'debug' => 'Member table not found'
                ], 500);
            }

            // Validate user exists
            $userExists = DB::table('member')
                ->where('member_id', $user_id)
                ->exists();
                
            if (!$userExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'user_id' => $user_id
                ], 404);
            }

            // Check if match_reports table exists
            if (!Schema::hasTable('match_reports')) {
                \Log::error('Match reports table does not exist');
                return response()->json([
                    'status' => 'success',
                    'flagged' => false,
                    'debug' => 'Match reports table not found'
                ]);
            }

            try {
                // Get all reports for this user with reporting user details
                $reports = DB::table('match_reports as mr')
                    ->leftJoin('member as m', 'm.member_id', '=', 'mr.reporting_user_id')
                    ->where('mr.reported_user_id', $user_id)
                    ->whereIn('mr.status', ['pending', 'under_review', 'suspended'])
                    ->select(
                        'mr.report_id as id',
                        'mr.match_id',
                        'mr.reporting_user_id',
                        'm.user_name as reporting_username',
                        DB::raw('(CASE 
                            WHEN m.profile_image = "" THEN ""
                            ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", m.profile_image) 
                            END) AS reporting_profile_image'),
                        'mr.reason',
                        'mr.reporter_pov_url',
                        'mr.reported_pov_url',
                        'mr.status',
                        'mr.created_at',
                        'mr.updated_at'
                    )
                    ->get();

                // Check if user has any suspended reports
                $suspendedReport = $reports->firstWhere('status', 'suspended');
                
                if ($suspendedReport) {
                    return response()->json([
                        'status' => 'success',
                        'flagged' => true,
                        'suspended' => true,
                        'message' => 'Your account has been suspended',
                        'reports' => $reports,
                        'debug' => 'User is suspended'
                    ]);
                } elseif ($reports->isNotEmpty()) {
                    return response()->json([
                        'status' => 'success',
                        'flagged' => true,
                        'suspended' => false,
                        'reports' => $reports,
                        'debug' => 'Found ' . $reports->count() . ' active reports'
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'flagged' => false,
                    'debug' => 'No active reports found for user'
                ]);

            } catch (\Exception $queryException) {
                \Log::error('Database query error: ' . $queryException->getMessage());
                return response()->json([
                    'status' => 'success',
                    'flagged' => false,
                    'debug' => 'Error querying reports: ' . $queryException->getMessage()
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error checking user flag status: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to check report status. Please try again.'
            ], 500);
        }
    }

    /**
     * Upload POV for a report (reported user's perspective)
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPov(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'report_id' => 'required|integer|exists:match_reports,report_id',
            'reported_user_id' => 'required|integer|exists:member,member_id',
            'reported_pov_url' => 'required|url|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if the report exists and belongs to this user
            $report = DB::table('match_reports')
                ->where('report_id', $request->report_id)
                ->where('reported_user_id', $request->reported_user_id)
                ->first();

            if (!$report) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Report not found or does not belong to this user'
                ], 404);
            }

            // Update the report with the POV URL
            $updated = DB::table('match_reports')
                ->where('report_id', $request->report_id)
                ->update([
                    'reported_pov_url' => $request->reported_pov_url,
                    'reported_pov_uploaded' => true,
                    'status' => 'under_review',
                    'updated_at' => \Carbon\Carbon::now(),
                ]);

            if ($updated) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'POV uploaded successfully. Awaiting admin review.',
                    'status' => 'under_review'
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update report with POV'
            ], 500);

        } catch (\Exception $e) {
            \Log::error('Failed to upload POV: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process POV upload. Please try again later.'
            ], 500);
        }
    }
    /**
     * Get a specific report by ID with user details
     * 
     * @param int $report_id
     * @param int $user_id (authenticated user ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReportById($report_id, $user_id)
    {
        try {
            // Validate the user exists
            $userExists = DB::table('member')
                ->where('member_id', $user_id)
                ->exists();
                
            if (!$userExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'user_id' => $user_id
                ], 404);
            }
    
            // Get report with user details
            $report = DB::table('match_reports as mr')
                ->leftJoin('member as reporter', 'reporter.member_id', '=', 'mr.reporting_user_id')
                ->leftJoin('member as reported', 'reported.member_id', '=', 'mr.reported_user_id')
                ->where('mr.report_id', $report_id)
                ->where(function($query) use ($user_id) {
                    $query->where('mr.reporting_user_id', $user_id)
                          ->orWhere('mr.reported_user_id', $user_id);
                })
                ->select(
                    'mr.report_id',
                    'mr.match_id',
                    'mr.reason',
                    'mr.reporter_pov_url',
                    'mr.reported_pov_url',
                    'mr.status',
                    'mr.created_at',
                    
                    // Reporter details
                    'mr.reporting_user_id',
                    'reporter.user_name as reporter_username',
                    'reporter.first_name as reporter_first_name',
                    'reporter.last_name as reporter_last_name',
                    DB::raw('(CASE 
                        WHEN reporter.profile_image = "" THEN ""
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", reporter.profile_image) 
                        END) AS reporter_profile_image'),
                    
                    // Reported user details
                    'mr.reported_user_id',
                    'reported.user_name as reported_username',
                    'reported.first_name as reported_first_name',
                    'reported.last_name as reported_last_name',
                    DB::raw('(CASE 
                        WHEN reported.profile_image = "" THEN ""
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", reported.profile_image) 
                        END) AS reported_profile_image')
                )
                ->first();
    
            if (!$report) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Report not found or access denied'
                ], 404);
            }
    
            return response()->json([
                'status' => 'success',
                'data' => $report
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error fetching report: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get all reports made by a specific user (as reporter)
     * 
     * @param int $user_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReportsMadeByUser($user_id)
    {
        try {
            // Validate user exists
            $userExists = DB::table('member')
                ->where('member_id', $user_id)
                ->exists();
                
            if (!$userExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'user_id' => $user_id
                ], 404);
            }
    
            // Get reports made by this user
            $reports = DB::table('match_reports as mr')
                ->join('member as reported', 'reported.member_id', '=', 'mr.reported_user_id')
                ->where('mr.reporting_user_id', $user_id)
                ->select(
                    'mr.report_id',
                    'mr.match_id',
                    'mr.reason',
                    'mr.reporter_pov_url',
                    'mr.reported_pov_url',
                    'mr.status',
                    'mr.created_at',
                    'mr.updated_at',
                    
                    // Reported user details
                    'mr.reported_user_id',
                    'reported.user_name as reported_username',
                    'reported.first_name as reported_first_name',
                    'reported.last_name as reported_last_name',
                    DB::raw('(CASE 
                        WHEN reported.profile_image = "" THEN ""
                        ELSE CONCAT ("' . $this->base_url . '/' . $this->system_config['admin_photo'] . '/profile_image/thumb/100x100_", reported.profile_image) 
                        END) AS reported_profile_image')
                )
                ->orderBy('mr.created_at', 'DESC')
                ->get();
    
            return response()->json([
                'status' => 'success',
                'count' => count($reports),
                'reports' => $reports
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error fetching user reports: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
    public function getMatchkill(Request $request, $matchId)
    {
        // Manually validate the route param
        $validator = Validator::make(['match_id' => $matchId], [
            'match_id' => 'required|integer',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid match ID',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        // Fetch data
        $match = DB::table('matches')->where('m_id', $matchId)->first();
    
        if (!$match) {
            return response()->json([
                'status' => false,
                'message' => 'Match not found',
            ], 404);
        }
    
        return response()->json([
            'status' => true,
            'per_kill' => $match->per_kill,
            'win_prize' => $match->win_prize,
        ]);
    }
    public function checkUserFlag($user_id)
    {
        try {
            // Check if user exists
            $user = DB::table('member')->where('member_id', $user_id)->first();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found'
                ], 404);
            }
    
            // Check for active reports against this user
            $reports = DB::table('match_reports')
                ->where('reported_user_id', $user_id)
                ->whereIn('status', ['pending', 'under_review', 'suspended'])
                ->exists();
    
            return response()->json([
                'status' => 'success',
                'flagged' => $reports,
                'suspended' => $user->account_status === 'suspended'
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function checkUserReportStatus($user_id)
    {
        try {
            // First validate the user exists
            $userExists = DB::table('member')
                ->where('member_id', $user_id)
                ->exists();
                
            if (!$userExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                    'user_id' => $user_id
                ], 404);
            }
    
            // Check if user has any active reports against them
            $hasActiveReports = DB::table('match_reports')
                ->where('reported_user_id', $user_id)
                ->whereIn('status', ['pending', 'under_review', 'suspended'])
                ->exists();
    
            return response()->json([
                'status' => 'success',
                'user_id' => $user_id,
                'is_reported' => $hasActiveReports,
                'message' => $hasActiveReports ? 'User has active reports' : 'No active reports found'
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error checking user report status: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to check report status. Please try again.'
            ], 500);
        }
    }

    /**
     * Check daily bonus eligibility for a user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkDailyBonusEligibility(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:member,member_id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user_id = $request->user_id;
            $today = Carbon::now($this->timezone)->format('Y-m-d');

            // Check if user already claimed bonus today using accountstatement table
            $alreadyClaimed = DB::table('accountstatement')
                ->where('member_id', $user_id)
                ->where('note_id', '14') // 14 = daily bonus
                ->where('note', 'Daily Bonus Claimed') // Filter for daily bonus transactions
                ->whereDate('accountstatement_dateCreated', $today)
                ->exists();

            if ($alreadyClaimed) {
                return response()->json([
                    'status' => 'success',
                    'eligible' => false,
                    'message' => 'Daily bonus already claimed today',
                    'next_claim_date' => Carbon::now($this->timezone)->addDay()->format('Y-m-d'),
                    'today_claimed' => true
                ]);
            }

            // Check if user joined at least 1 match today with minimum 10 coins
            // Use correct table for member match joins
            $matchesJoined = DB::table('match_join_member as mj')
                ->join('matches as m', 'mj.match_id', '=', 'm.m_id')
                ->where('mj.member_id', $user_id)
                ->where('m.entry_fee', '>=', 10)
                ->whereDate('mj.date_craeted', $today)
                ->count();

            $eligible = $matchesJoined >= 1;

            return response()->json([
                'status' => 'success',
                'eligible' => $eligible,
                'matches_joined_today' => $matchesJoined,
                'message' => $eligible ? 'Eligible for daily bonus' : 'Not eligible - need to join at least 1 match with minimum 10 coins today',
                'next_claim_date' => $eligible ? null : Carbon::now($this->timezone)->addDay()->format('Y-m-d'),
                'today_claimed' => false
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Claim daily bonus for a user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function claimDailyBonus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:member,member_id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user_id = $request->user_id;
            $today = Carbon::now($this->timezone)->format('Y-m-d');

            // Determine bonus amount sequence (load from DB if available)
            $bonus_sequence = DB::table('progressive_bonus_config')
                ->where('is_active', 1)
                ->orderBy('sequence_order')
                ->pluck('bonus_amount')
                ->map(function ($v) { return floatval($v); })
                ->toArray();
            if (empty($bonus_sequence)) {
                // Fallback default sequence
                $bonus_sequence = [0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.5];
            }
            $bonus_amount = $bonus_sequence[0]; // default if no streak or previous claim

            // Fetch most recent bonus claim - using note_id 14 (daily bonus)
            $lastBonus = DB::table('accountstatement')
                ->where('member_id', $user_id)
                ->where('note_id', '14') // 14 = daily bonus
                ->where('note', 'Daily Bonus Claimed') // Additional filter to distinguish from watch and earn
                ->orderBy('accountstatement_dateCreated', 'desc')
                ->first();

            if ($lastBonus) {
                $lastClaimDate = Carbon::parse($lastBonus->accountstatement_dateCreated, $this->timezone)->startOfDay();
                $yesterday = Carbon::now($this->timezone)->subDay()->startOfDay();

                // Continue streak only if last claim was exactly yesterday
                if ($lastClaimDate->eq($yesterday)) {
                    $prevAmount = floatval($lastBonus->deposit);
                    $idx = array_search($prevAmount, $bonus_sequence);
                    if ($idx === false) {
                        // If previous amount not in sequence, reset
                        $bonus_amount = 0.5;
                    } else {
                        // Next amount in cycle (wrap around)
                        $bonus_amount = $bonus_sequence[($idx + 1) % count($bonus_sequence)];
                    }
                }
            }

            // Check eligibility first
            $eligibilityResponse = $this->checkDailyBonusEligibility($request);
            $eligibilityData = json_decode($eligibilityResponse->getContent(), true);

            if (!$eligibilityData['eligible']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $eligibilityData['message'],
                    'eligible' => false
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Get current user balance with lock
                $user = DB::table('member')
                    ->where('member_id', $user_id)
                    ->lockForUpdate()
                    ->first();

                if (!$user) {
                    throw new \Exception('User not found');
                }

                $current_balance = floatval($user->join_money === null ? 0 : $user->join_money);

                // Debug: Log the values before update
                \Log::info('Daily bonus claim - Before update', [
                    'user_id' => $user_id,
                    'current_balance' => $current_balance,
                    'bonus' => $bonus_amount,
                ]);

                // Atomically increment join_money to avoid no-op updates and race conditions
                $updateResult = DB::update(
                    'UPDATE member SET join_money = COALESCE(join_money, 0) + ? WHERE member_id = ?',
                    [$bonus_amount, $user_id]
                );

                // Reload the row to get the updated balance (also covers the case when DB reports 0 affected rows)
                $updatedUser = DB::table('member')
                    ->where('member_id', $user_id)
                    ->lockForUpdate()
                    ->first();

                if (!$updatedUser) {
                    throw new \Exception('Failed to reload user after balance update');
                }

                $new_balance = floatval($updatedUser->join_money === null ? 0 : $updatedUser->join_money);

                // If DB reported 0 affected rows, try fallbacks and verify explicitly
                if ($updateResult === 0) {
                    $expected_balance = $current_balance + $bonus_amount;

                    // Fallback #1: use query builder increment
                    $incResult = DB::table('member')
                        ->where('member_id', $user_id)
                        ->increment('join_money', $bonus_amount);

                    $updatedUser = DB::table('member')
                        ->where('member_id', $user_id)
                        ->lockForUpdate()
                        ->first();
                    $new_balance = floatval($updatedUser && $updatedUser->join_money !== null ? $updatedUser->join_money : 0);

                    if ($incResult === 0 || abs($new_balance - $expected_balance) > 0.001) {
                        // Fallback #2: set explicit value
                        $setResult = DB::update(
                            'UPDATE member SET join_money = ? WHERE member_id = ? LIMIT 1',
                            [$expected_balance, $user_id]
                        );
                        $updatedUser = DB::table('member')
                            ->where('member_id', $user_id)
                            ->lockForUpdate()
                            ->first();
                        $new_balance = floatval($updatedUser && $updatedUser->join_money !== null ? $updatedUser->join_money : 0);

                        if ($setResult === 0 || abs($new_balance - $expected_balance) > 0.001) {
                            throw new \Exception(
                                'Failed to update user balance after fallbacks. '
                                . 'raw_update=' . $updateResult
                                . ', qb_increment=' . ($incResult ?? 'null')
                                . ', qb_set=' . ($setResult ?? 'null')
                                . ' | context: user_id=' . $user_id
                                . ', bonus=' . $bonus_amount
                                . ', prev_balance=' . $current_balance
                                . ', expected_balance=' . $expected_balance
                                . ', reloaded_balance=' . $new_balance
                            );
                        }
                    }
                }

                // Create transaction record in accountstatement table
                // Using note_id '14' (daily bonus)
                $transactionData = [
                    'member_id' => $user_id,
                    'pubg_id' => $user->pubg_id ?? '',
                    'from_mem_id' => 0,
                    'deposit' => $bonus_amount, // Daily bonus credited as deposit
                    'withdraw' => 0,
                    'join_money' => $new_balance,
                    'win_money' => 0,
                    'match_id' => 0,
                    'note' => 'Daily Bonus Claimed',
                    'note_id' => '14', // 14 = daily bonus
                    'pyatmnumber' => '',
                    'withdraw_method' => '',
                    'entry_from' => '1', // 1=app
                    'ip_detail' => $request->ip(),
                    'browser' => $request->header('User-Agent', 'Unknown'),
                    'accountstatement_dateCreated' => Carbon::now($this->timezone),
                    'lottery_id' => 0,
                    'order_id' => 0
                ];

                // Use raw insert with explicit columns to avoid any mutations
                $bonus_amount_db = number_format((float)$bonus_amount, 2, '.', '');
                $new_balance_db = number_format((float)$new_balance, 2, '.', '');
                $insertOk = DB::insert(
                    'INSERT INTO accountstatement (`member_id`,`pubg_id`,`from_mem_id`,`deposit`,`withdraw`,`join_money`,`win_money`,`match_id`,`note`,`note_id`,`pyatmnumber`,`withdraw_method`,`entry_from`,`ip_detail`,`browser`,`accountstatement_dateCreated`,`lottery_id`,`order_id`) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $user_id,
                        $user->pubg_id ?? '',
                        0,
                        $bonus_amount_db,
                        0,
                        $new_balance_db,
                        0,
                        0,
                        'Daily Bonus Claimed',
                        '14',
                        '',
                        '',
                        '1',
                        $request->ip(),
                        $request->header('User-Agent', 'Unknown'),
                        Carbon::now($this->timezone),
                        0,
                        0,
                    ]
                );

                if (!$insertOk) {
                    throw new \Exception('Failed to create transaction record');
                }

                $transaction_id = DB::getPdo()->lastInsertId();

                // Verify ledger row reflects deposit amount and correct note_id
                $insertedTxn = DB::table('accountstatement')
                    ->where('account_statement_id', $transaction_id)
                    ->first();

                if (!$insertedTxn) {
                    throw new \Exception('Failed to load inserted transaction for verification');
                }

                $insertedDeposit = isset($insertedTxn->deposit) ? floatval($insertedTxn->deposit) : null;
                $insertedNoteId = isset($insertedTxn->note_id) ? (string)$insertedTxn->note_id : null;
                if ($insertedDeposit === null || abs($insertedDeposit - floatval($bonus_amount)) > 0.001 || $insertedNoteId !== '14') {
                    throw new \Exception('Daily bonus ledger mismatch after insert: deposit=' . var_export($insertedDeposit, true) . ', expected=' . $bonus_amount . ', note_id=' . var_export($insertedNoteId, true));
                }

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Daily bonus claimed successfully',
                    'data' => [
                        'bonus_amount' => $bonus_amount,
                        'previous_balance' => $current_balance,
                        'new_balance' => $new_balance,
                        'transaction_id' => $transaction_id,
                        'claim_date' => $today
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                // Log the error for debugging
                \Log::error('Daily bonus claim error: ' . $e->getMessage());
                throw $e;
            }

        } catch (\Exception $e) {
            // Provide detailed diagnostics in error response
            try {
                $diagUserId = $request->user_id ?? null;
                $diagRow = null;
                if ($diagUserId) {
                    $diagRow = DB::table('member')->select('member_id','join_money')->where('member_id', $diagUserId)->first();
                }
            } catch (\Throwable $t) {
                $diagRow = null;
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage(),
                'details' => [
                    'exception' => get_class($e),
                    'user_id' => $request->user_id ?? null,
                    'bonus_amount' => isset($bonus_amount) ? $bonus_amount : null,
                    'update_result' => isset($updateResult) ? $updateResult : null,
                    'previous_balance' => isset($current_balance) ? $current_balance : null,
                    'reloaded_balance' => isset($new_balance) ? $new_balance : (isset($diagRow->join_money) ? floatval($diagRow->join_money) : null),
                ]
            ], 500);
        }
    }

    /**
     * Get daily bonus history for a user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDailyBonusHistory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:member,member_id',
                'limit' => 'integer|min:1|max:100',
                'offset' => 'integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user_id = $request->user_id;
            $limit = $request->limit ?? 20;
            $offset = $request->offset ?? 0;

            // Get bonus history from accountstatement table
            $bonusHistory = DB::table('accountstatement')
                ->where('member_id', $user_id)
                ->where('note_id', '14') // 14 = daily bonus
                ->where('note', 'Daily Bonus Claimed') // Filter for daily bonus transactions
                ->orderBy('accountstatement_dateCreated', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Get total count
            $totalCount = DB::table('accountstatement')
                ->where('member_id', $user_id)
                ->where('note_id', '14')
                ->where('note', 'Daily Bonus Claimed')
                ->count();

            // Get total bonus claimed
            $totalBonusClaimed = DB::table('accountstatement')
                ->where('member_id', $user_id)
                ->where('note_id', '14')
                ->where('note', 'Daily Bonus Claimed')
                ->sum('deposit');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'bonus_history' => $bonusHistory,
                    'pagination' => [
                        'total_count' => $totalCount,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $totalCount
                    ],
                    'summary' => [
                        'total_bonus_claimed' => $totalBonusClaimed,
                        'total_claims' => $totalCount
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's daily bonus progress: which sequence_order was last claimed
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDailyBonusProgress(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:member,member_id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $userId = $request->user_id;

            // Get latest daily-bonus claim for the user
            $lastClaim = DB::table('accountstatement')
                ->where('member_id', $userId)
                ->where('note_id', '14') // 14 = daily bonus
                ->where('note', 'Daily Bonus Claimed')
                ->orderBy('accountstatement_dateCreated', 'desc')
                ->first();

            if (!$lastClaim) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'sequence_order' => null,
                        'bonus_amount' => null,
                        'last_claim_date' => null,
                        'total_claims' => 0,
                        'todays_sequence_order' => 1,
                        'already_claimed_today' => false
                    ],
                    'message' => 'No daily bonus claimed yet'
                ]);
            }

            $depositAmount = floatval($lastClaim->deposit ?? 0);

            // Load active progressive config and match with tolerance for floating values
            $configs = DB::table('progressive_bonus_config')
                ->where('is_active', 1)
                ->orderBy('sequence_order')
                ->get();

            $sequenceOrder = null;
            $matchedBy = 'none';
            $closestOrder = null;
            $closestDiff = INF;

            foreach ($configs as $row) {
                $amount = floatval($row->bonus_amount);
                $diff = abs($amount - $depositAmount);
                if ($diff < 0.011) { // ~1 cent tolerance
                    $sequenceOrder = intval($row->sequence_order);
                    $matchedBy = 'exact_tolerance';
                    break;
                }
                if ($diff < $closestDiff) {
                    $closestDiff = $diff;
                    $closestOrder = intval($row->sequence_order);
                }
            }
            if ($sequenceOrder === null && $closestOrder !== null) {
                // Fallback to closest if within reasonable range (<= 0.1)
                if ($closestDiff <= 0.1) {
                    $sequenceOrder = $closestOrder;
                    $matchedBy = 'closest_match';
                }
            }

            // Count total claims
            $totalClaims = DB::table('accountstatement')
                ->where('member_id', $userId)
                ->where('note_id', '14')
                ->where('note', 'Daily Bonus Claimed')
                ->count();

            // Determine today's expected sequence_order based on streak rule
            $yesterday = Carbon::now($this->timezone)->subDay()->startOfDay();
            $today = Carbon::now($this->timezone)->startOfDay();
            $lastClaimDate = Carbon::parse($lastClaim->accountstatement_dateCreated, $this->timezone)->startOfDay();

            $todaysSequenceOrder = 1;
            $alreadyClaimedToday = false;

            if (!is_null($sequenceOrder)) {
                if ($lastClaimDate->eq($today)) {
                    // Already claimed today → keep same order
                    $todaysSequenceOrder = $sequenceOrder;
                    $alreadyClaimedToday = true;
                } elseif ($lastClaimDate->eq($yesterday)) {
                    // Continue streak
                    $todaysSequenceOrder = ($sequenceOrder % 7) + 1; // wrap 7 -> 1
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'sequence_order' => $sequenceOrder,
                    'bonus_amount' => $depositAmount,
                    'last_claim_date' => $lastClaim->accountstatement_dateCreated,
                    'total_claims' => $totalClaims,
                    'matched_by' => $matchedBy,
                    'todays_sequence_order' => $todaysSequenceOrder,
                    'already_claimed_today' => $alreadyClaimedToday,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get daily bonus statistics for admin
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDailyBonusStats(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date_from' => 'date_format:Y-m-d',
                'date_to' => 'date_format:Y-m-d|after_or_equal:date_from'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $dateFrom = $request->date_from ?? Carbon::now($this->timezone)->subDays(30)->format('Y-m-d');
            $dateTo = $request->date_to ?? Carbon::now($this->timezone)->format('Y-m-d');

            // Get daily bonus statistics from accountstatement table
            $stats = DB::table('accountstatement')
                ->where('note_id', '14') // 14 = daily bonus
                ->where('note', 'Daily Bonus Claimed') // Filter for daily bonus transactions
                ->whereBetween('accountstatement_dateCreated', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->selectRaw('
                    COUNT(*) as total_claims,
                    SUM(deposit) as total_bonus_paid,
                    AVG(deposit) as average_bonus,
                    DATE(accountstatement_dateCreated) as claim_date
                ')
                ->groupBy('claim_date')
                ->orderBy('claim_date', 'desc')
                ->get();

            // Get overall statistics
            $overallStats = DB::table('accountstatement')
                ->where('note_id', '14')
                ->where('note', 'Daily Bonus Claimed')
                ->whereBetween('accountstatement_dateCreated', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->selectRaw('
                    COUNT(*) as total_claims,
                    SUM(deposit) as total_bonus_paid,
                    AVG(deposit) as average_bonus,
                    COUNT(DISTINCT member_id) as unique_users
                ')
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ],
                    'daily_stats' => $stats,
                    'overall_stats' => $overallStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily bonus configuration for all days of the week
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDailyBonusConfig()
    {
        try {
            $config = DB::table('progressive_bonus_config')
                ->where('is_active', 1)
                ->orderBy('sequence_order')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $config
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update daily bonus configuration for a specific day
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDailyBonusConfig(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sequence_order' => 'required|integer|min:1',
                'bonus_amount' => 'required|numeric|min:0',
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $sequenceOrder = $request->sequence_order;
            $bonusAmount = $request->bonus_amount;
            $description = $request->description ?? '';
            $isActive = $request->is_active ?? true;

            // Update or insert configuration
            $updated = DB::table('progressive_bonus_config')
                ->where('sequence_order', $sequenceOrder)
                ->update([
                    'bonus_amount' => $bonusAmount,
                    'description' => $description,
                    'is_active' => $isActive ? 1 : 0,
                    'updated_at' => Carbon::now($this->timezone)
                ]);

            if ($updated === 0) {
                // Insert new configuration if not exists
                DB::table('progressive_bonus_config')->insert([
                    'sequence_order' => $sequenceOrder,
                    'bonus_amount' => $bonusAmount,
                    'description' => $description,
                    'is_active' => $isActive ? 1 : 0,
                    'created_at' => Carbon::now($this->timezone),
                    'updated_at' => Carbon::now($this->timezone)
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Daily bonus configuration updated successfully',
                'data' => [
                    'sequence_order' => $sequenceOrder,
                    'bonus_amount' => $bonusAmount,
                    'description' => $description,
                    'is_active' => $isActive
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's bonus amount based on configuration
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTodaysBonusAmount()
    {
        try {
            $today = Carbon::now($this->timezone);
            // Map day to a sequence position (1..7), Sunday as 7
            $dayOfWeek = $today->dayOfWeek; // 0..6
            $sequenceOrder = $dayOfWeek === 0 ? 7 : $dayOfWeek; // 1..7

            $config = DB::table('progressive_bonus_config')
                ->where('sequence_order', $sequenceOrder)
                ->where('is_active', 1)
                ->first();

            if (!$config) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No progressive bonus configuration found for today'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'sequence_order' => $sequenceOrder,
                    'day_name' => $today->format('l'),
                    'bonus_amount' => $config->bonus_amount,
                    'description' => $config->description,
                    'date' => $today->format('Y-m-d')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset daily bonus configuration to default values
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetDailyBonusConfig()
    {
        try {
            // Delete existing configuration
            DB::table('progressive_bonus_config')->truncate();

            // Insert default configuration
            $defaultConfig = [
                ['day_of_week' => 1, 'bonus_amount' => 0.50, 'description' => 'Monday Bonus', 'is_active' => 1],
                ['day_of_week' => 2, 'bonus_amount' => 1.00, 'description' => 'Tuesday Bonus', 'is_active' => 1],
                ['day_of_week' => 3, 'bonus_amount' => 1.50, 'description' => 'Wednesday Bonus', 'is_active' => 1],
                ['day_of_week' => 4, 'bonus_amount' => 2.00, 'description' => 'Thursday Bonus', 'is_active' => 1],
                ['day_of_week' => 5, 'bonus_amount' => 2.50, 'description' => 'Friday Bonus', 'is_active' => 1],
                ['day_of_week' => 6, 'bonus_amount' => 3.00, 'description' => 'Saturday Bonus', 'is_active' => 1],
                ['day_of_week' => 7, 'bonus_amount' => 4.00, 'description' => 'Sunday Bonus', 'is_active' => 1]
            ];

            foreach ($defaultConfig as $config) {
                $config['created_at'] = Carbon::now($this->timezone);
                $config['updated_at'] = Carbon::now($this->timezone);
                DB::table('progressive_bonus_config')->insert($config);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Daily bonus configuration reset to default values',
                'data' => $defaultConfig
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
    public function notifyMatchPlayers($match_id) {
        try {
            // Fetch match with game and room details
            $match = DB::table('matches as m')
                ->leftJoin('game as g', 'g.game_id', '=', 'm.game_id')
                ->where('m.m_id', $match_id)
                ->select(
                    'm.m_id',
                    'm.match_name',
                    'm.roomid',
                    'm.room_pass',
                    'm.game_id',
                    DB::raw('COALESCE(g.game_name, g.package_name) as game_name')
                )
                ->first();
    
            if (!$match) {
                return response()->json([
                    'status'  => 'false',
                    'title'   => 'Error!',
                    'message' => 'Match not found'
                ]);
            }
    
            // Proper heading with emojis 🎮
            $heading = "🎮 #". ($match->m_id ?? '#') 
                     . " - " . ($match->game_name ?? 'Game') 
                     . "ID Pass is available! 🚀";
    
            // All joined members with push tokens
            $players = DB::table('match_join_member as mj')
                ->join('member as mem', 'mem.member_id', '=', 'mj.member_id')
                ->where('mj.match_id', $match_id)
                ->select('mj.member_id', 'mem.user_name', 'mem.player_id', 'mem.push_noti')
                ->get();
    
            if ($players->isEmpty()) {
                return response()->json([
                    'status'  => 'true',
                    'title'   => 'Success!',
                    'message' => 'No players joined; nothing to notify.'
                ]);
            }
    
            $sentCount = 0;
    
            foreach ($players as $p) {
                if (!$p->push_noti || empty($p->player_id)) {
                    continue;
                }
    
                // Personalized content for each player
                $content = "👋 Dear {$p->user_name},\n"
                    . "Your room details for *" 
                    . ($match->game_name ?? 'Game') . " - #"
                    . ($match->m_id ?? '#') . "* are:\n\n"
                    . "🆔 ID: " . ($match->roomid ?? '-') . "\n"
                    . "🔑 Pass: " . ($match->room_pass ?? '-') . "\n\n"
                    . "Good luck & have fun! 🚀🔥";
    
                // Send notification
                $ok = $this->send_onesignal_noti(
                    $heading,
                    $content,
                    $p->player_id,
                    $p->member_id,
                    $match->game_id,
                    false
                );
    
                if ($ok) {
                    $sentCount++;
                }
            }
    
            return response()->json([
                'status'  => 'true',
                'title'   => 'Success!',
                'message' => 'Notifications sent: ' . $sentCount
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'false',
                'title'   => 'Error!',
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }
    public function getAdsUntil()
    {
        try {
            return response()->json([
                'status' => 'success',
                'adsuntil' => 6
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }



}
/* ===============SQL-Queries-Update===============

ALTER TABLE member
ADD COLUMN mob_verify BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN mob_verify_otp VARCHAR(6) NULL DEFAULT NULL AFTER mob_verify;


ALTER TABLE member
ADD COLUMN `referral_bonus_given` TINYINT(1) DEFAULT 0;


ALTER TABLE matches
ADD COLUMN roomid VARCHAR(100) NOT NULL AFTER match_desc,
ADD COLUMN room_pass VARCHAR(100) NOT NULL AFTER roomid;


CREATE TABLE match_reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(50) NOT NULL,
    reporting_user_id INT NOT NULL,
    reported_user_id INT NOT NULL,
    reason TEXT NOT NULL,

    reporter_pov_url VARCHAR(255),        -- Reporter POV
    reported_pov_url VARCHAR(255),        -- Reported user POV
    reporter_pov_uploaded BOOLEAN DEFAULT FALSE,
    reported_pov_uploaded BOOLEAN DEFAULT FALSE,

    status ENUM('pending', 'under_review', 'cleared', 'suspended') DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    action_date TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (reporting_user_id) REFERENCES member(member_id),
    FOREIGN KEY (reported_user_id) REFERENCES member(member_id)
);

-- Daily Bonus System - Update accountstatement table
-- Note: Using existing join_money field in member table for daily bonus

-- Update note_id enum to include daily bonus (14)
-- Note: This requires manual database update as ALTER ENUM is complex
-- The note_id 14 represents: Daily Bonus
-- Current note_id values: 0-13, adding 14 for daily bonus
-- 0 = add money to join wallet,1 = withdraw from win wallet,2 = match join,3 = register referral,4 = referral,5 = match reward,6 = refund,7 = add money to win wallet,8 = withdraw from join wallet,9 = pending withdraw,10 = Lottery Joined,11 = Lottery Reward,12=product order,13=watch and earn,14=daily bonus


-- Daily Bonus Configuration Table
CREATE TABLE progressive_bonus_config (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sequence_order INT NOT NULL,
  bonus_amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO progressive_bonus_config (sequence_order, bonus_amount, description)
VALUES (1,0.50,'Step 1'),(2,1.00,'Step 2'),
       (3,1.50,'Step 3'),(4,2.00,'Step 4'),
       (5,2.50,'Step 5'),(6,3.00,'Step 6'),
       (7,3.50,'Step 7');
===============SQL-Queries-Update=============== */


