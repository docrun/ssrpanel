<?php

namespace App\Http\Controllers;

use App\Http\Models\SsGroup;
use App\Http\Models\SsGroupNode;
use App\Http\Models\SsNode;
use App\Http\Models\User;
use App\Http\Models\UserScoreLog;
use App\Http\Models\UserSubscribe;
use App\Http\Models\UserSubscribeLog;
use Illuminate\Http\Request;
use Response;
use Redirect;
use Cache;

/**
 * 订阅控制器
 * Class SubscribeController
 * @package App\Http\Controllers
 */
class SubscribeController extends BaseController
{
    protected static $config;

    function __construct()
    {
        self::$config = $this->systemConfig();
    }

    // 登录页
    public function index(Request $request, $code)
    {
        if (empty($code)) {
            return Redirect::to('login');
        }

        // 校验合法性
        $subscribe = UserSubscribe::where('code', $code)->with('user')->first();
        if (empty($subscribe)) {
            exit('非法请求');
        }

        $user = User::where('id', $subscribe->user_id)->whereIn('status', [0, 1])->where('enable', 1)->first();
        if (empty($user)) {
            exit('非法请求');
        }

        // 更新访问次数
        $subscribe->increment('times', 1);

        // 记录每次请求
        $log = new UserSubscribeLog();
        $log->sid = $subscribe->id;
        $log->request_ip = $request->getClientIp();
        $log->request_time = date('Y-m-d H:i:s');
        $log->save();

        // 获取这个账号可用节点
        $group_ids = SsGroup::where('level', '<=', $user->level)->select(['id'])->get();
        if (empty($group_ids)) {
            exit();
        }

        $node_ids = SsGroupNode::whereIn('group_id', $group_ids)->select(['node_id'])->get();
        $nodeList = SsNode::whereIn('id', $node_ids)->get();
        $scheme = [];
        foreach ($nodeList as $node) {
            $obfs_param = $user->obfs_param ? base64_encode($user->obfs_param) : '';
            $protocol_param = $user->protocol_param ? base64_encode($user->protocol_param) : '';

            // 生成ssr scheme
            $ssr_str = '';
            $ssr_str .= $node->server . ':' . $user->port;
            $ssr_str .= ':' . $user->protocol . ':' . $user->method;
            $ssr_str .= ':' . $user->obfs . ':' . base64_encode($user->passwd);
            $ssr_str .= '/?obfsparam=' . $obfs_param;
            $ssr_str .= '&protoparam=' . $protocol_param;
            $ssr_str .= '&remarks=' . base64_encode($node->name);
            $ssr_str .= '&group=' . base64_encode('VPN');
            //$ssr_str .= '&udpport=0';
            //$ssr_str .= '&uot=0';
            $ssr_str = $this->base64url_encode($ssr_str);
            $scheme[] = 'ssr://' . $ssr_str;
        }

        foreach ($scheme as $vo) {
            echo $vo . "\n";
        }

        exit();
    }

}
