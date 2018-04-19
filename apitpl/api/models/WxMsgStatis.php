<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use api\models\NoticeRecv;
use api\models\ProvinceCityArea;
use api\models\Account;
use api\models\QyJumpcontact;
use api\models\QyJumpurl;

use api\models\StatisWork;
use api\models\StatisAlbum;
use api\models\StatisCommunity;
use api\models\StatisDirectmsg;
use api\models\StatisNotice;
use api\models\StatisScore;
use api\models\StatisSurvey;
use api\models\StatisVote;

use api\models\Work;
use api\models\Notice;
use api\models\NoticeDirect;
use api\models\Album;
use api\models\Community;
use api\models\ScoreExam;
use api\models\Survery;
use api\models\Vote;


use common\helps\functions;

class WxMsgStatis extends \yii\db\ActiveRecord{

    //  3:    家校圈
    //  4:    即时通讯
    //  6:    通知
    //  7:    作业
    //  11:   请假
    //  12:   投票
    //  13:   调研
    //  14:   定向消息

    public static function tableName()
    {
        return 'wx_msg_statis';
    }

    /**
     * 统计学校信息发送量
     * @param int  $province_id     省份ID
     * @param int  $city_id         城市ID
     * @param int  $area_id         地区ID 
     * @param int  $time            时间戳 
     * @param int  $type            时间筛选类型ID，4周，3月，2年
     * @param int  $appid           应用ID 
     */
    
    public function msgAccount($province_id,$city_id,$area_id,$time,$type,$appid='',$school_type='',$msg_type='')
    {
        
        $w = '';
        if(!empty($province_id))    $w['a.province_id'] = $province_id;
        if(!empty($city_id))        $w['a.city_id']     = $city_id;
        if(!empty($area_id))        $w['a.area_id']     = $area_id;
        if($school_type >= 0)       $w['a.school_type'] = $school_type;

        // 先从缓存里面读取数据，如果没有再从数据库获取
        $cache = Yii::$app->cache; 
        $cachePath = json_encode($w).$time.$type.$appid.$msg_type.'msgAccount';
        $data = $cache->get($cachePath);
 
        if ($data === false) { 
            if(!empty($time))  {
                if($type == 3){
                    $time = functions::getMonthRangeByTime($time);
                }elseif($type == 2){
                    $time = functions::getSevenDaysRangeByTime($time);
                }elseif($type == 4){
                    $time = functions::getYearRangeByTime($time);
                }
               
                $stime = $time['stime'];
                $etime = $time['etime'];
            }else{
                $stime = '';
                $etime = '';
            }

            //获取学校列表
            $qid_arr = [];
            $account = [];
            $account_list = Account::list($w);

            foreach ($account_list as $k => $v) {
                foreach ($v as $a => $school) {
                    $account[] = $school;
                    $qid_arr[] = intval($school['id']);
                }
            }
     
            //获取天数
            if($type == 3) {
                $date_type = 'd';
                //月份的每一天
                $days = functions::getMonthDays(date('Y-m',$stime),$date_type);
            }elseif ($type == 2) {
                $date_type = 'd';
                //周的每一天
                $days = functions::getSevenDaysByTime($stime,$date_type);
            }elseif ($type == 4) {
                $date_type = 'm';
                //年份的每个月
                $days = [];
                for ($i=0; $i < 12; $i++) { 
                    $days[$i]['date'] = strval($i+1);
                }
            }   

            // 读取发送信息总量
            //统计表统计的是前一天的数量，创建的时间是当天
            $appMsg   = $this->getAppMsgList($appid,$qid_arr,$stime,$etime,$msg_type);

            foreach ($account as $k => $v) {
            	$msgInfo = [];
            	foreach ($appMsg['work_count_res'] as $val) {
            		if($v['id'] == $val['qid']) $msgInfo[] = $val;
            	}
            	foreach ($appMsg['notice_count_res'] as $val) {
            		if($v['id'] == $val['qid']) $msgInfo[] = $val;
            	}
            	foreach ($appMsg['album_count_res'] as $val) {
            		if($v['id'] == $val['qid']) $msgInfo[] = $val;
            	}
            	foreach ($appMsg['directmsg_count_res'] as $val) {
            		if($v['id'] == $val['qid']) $msgInfo[] = $val;
            	}
            	foreach ($appMsg['survey_count_res'] as $val) {
            		if($v['id'] == $val['qid']) $msgInfo[] = $val;
            	}
            	foreach ($appMsg['score_count_res'] as $val) {
            		if($v['id'] == $val['qid']) $msgInfo[] = $val;
            	}
            	foreach ($appMsg['community_count_res'] as $val) {
            		if($v['id'] == $val['qid']) $msgInfo[] = $val;
            	}
            	foreach ($appMsg['vote_count_res'] as $val) {
            		if($v['id'] == $val['qid']) $msgInfo[] = $val;
            	}

            	$account[$k]['msgInfo'] = $msgInfo;
            }
      
            //统计每天发送总计
            foreach ($account as $k => $school) {
                foreach ($days as $a => $date) {
                    $send_num = 0;
                    foreach ($school['msgInfo'] as $msgInfo) {

                        if($date['date'] == date($date_type,$msgInfo['create_time'])){
                            $send_num ++;
                        }
                    }  

                    $days[$a]['send_num'] = $send_num;
                }   

               
                //去除msgInfo
                unset($account[$k]['msgInfo']);
                unset($account[$k]['city_id']);
                unset($account[$k]['area_id']);
                unset($account[$k]['province_id']);
                unset($account[$k]['day_send']);

                $new_days = [];
                $count_all = 0;
                foreach (array_values($days) as $kk => $day_info) {
                    $new_days[('count'.$kk)] = $day_info['send_num']; 
                    $count_all               += $day_info['send_num'];
                }

                $new_days = $this->thirty_days($new_days,count($new_days)-1,'count');

                $account[$k]['days']        = $new_days;
                $account[$k]['count_all']   = $count_all;
            }


            $data = $account;
            //这里我们可以操作数据库获取数据，然后通过$cache->set方法进行缓存 
            $cacheData = $data;
            //set方法的第一个参数是我们的数据对应的key值，方便我们获取到 
            //第二个参数即是我们要缓存的数据 
            //第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
            $cache->set($cachePath, $cacheData, 60*60*12); 
        }

        return $data;
    }


    /**
     * 统计地区信息发送量
     * @param int  $province_id     省份ID
     * @param int  $city_id         城市ID
     * @param int  $area_id         地区ID 
     * @param int  $time            时间戳 
     * @param int  $type            时间筛选类型ID，4周，3月，2年
     * @param int  $appid           应用ID 
     */
    public function msgArea($province_id,$city_id,$area_id,$time,$type,$appid,$msg_type)
    {

        $w = '';
        if(!empty($province_id))    $w['a.province_id'] = $province_id;
        if(!empty($city_id))        $w['a.city_id']     = $city_id;
        if(!empty($area_id))        $w['a.area_id']     = $area_id;
     
        // 先从缓存里面读取数据，如果没有再从数据库获取
        $cache = Yii::$app->cache; 
        $cachePath = json_encode($w).$time.$type.$appid.$msg_type.'msgArea';
        $data = $cache->get($cachePath);
   
        if ($data === false) {

            if(!empty($time))  {
                if($type == 3){
                    $time = functions::getMonthRangeByTime($time);
                }elseif($type == 2){
                    $time = functions::getSevenDaysRangeByTime($time);
                }elseif($type == 4){
                    $time = functions::getYearRangeByTime($time);
                }
               
                $stime = $time['stime'];
                $etime = $time['etime'];
            }else{
                $stime = '';
                $etime = '';
            }

            //获取区
            $areas = ProvinceCityArea::areaList($province_id,$city_id,$area_id);

            //获取学校列表
            $account = Account::list($w);
            //获取天数
            if($type == 3) {
                $date_type = 'd';
                //月份的每一天
                $days = functions::getMonthDays(date('Y-m',$stime),$date_type);
            }elseif ($type == 2) {
                $date_type = 'd';
                //周的每一天
                $days = functions::getSevenDaysByTime($stime,$date_type);
            }elseif ($type == 4) {
                $date_type = 'm';
                //1年12个月
                $days = [];
                for ($i=0; $i < 12; $i++) { 
                    $days[$i]['date'] = strval($i+1);
                }
            }    
            
            foreach ($areas as $k => $v) {

                $qid_arr = [];
                foreach ($account as $area_id => $school) {
                    if($v['id'] == $area_id){
                        $areas[$k]['area_name'] = $school[0]['area_name'];
                        foreach ($school as $school_info) {
                            $qid_arr[] = intval($school_info['id']);
                        }
                    }
                }
                
                //统计所有学校所有应用的消息发送量
                $appMsg = array_values($this->getAppMsgList($appid,$qid_arr,$stime,$etime,$msg_type));          

               	$msgList = [];
               	foreach ($appMsg as $list) {
               		foreach ($list as $value) {
               			$msgList[] = $value;
               		}
               	}


                //每天发送总计
                foreach ($days as $a => $date) {

                    $send_num = 0;
                 	foreach ($msgList  as $msgInfo) {
                 		
                 		if($date['date'] == date($date_type,$msgInfo['create_time'])){
                            $send_num ++;
                        }
                    }  

                    $days[$a]['send_num'] = $send_num;
                }
                
                //合计
                $new_days = [];
                $count_all = 0;
                foreach (array_values($days) as $a => $date) {
                    $new_days[('count'.$a)]  = $date['send_num'];
                    $count_all               += $date['send_num'];
                }

                //返回31行
                $new_days = $this->thirty_days($new_days,count($new_days)-1,'count');

                $areas[$k]['days'] = $new_days;
                $areas[$k]['count_all'] = $count_all;
            }

            //判断所有区是否都有学校,缺少area_name
            foreach ($areas as $k => $v) {
                if(empty($v['area_name']))  $areas[$k]['area_name'] = ProvinceCityArea::area_name($v['id']);
            }


            $data = $areas;
            //这里我们可以操作数据库获取数据，然后通过$cache->set方法进行缓存 
            $cacheData = $data;
            //set方法的第一个参数是我们的数据对应的key值，方便我们获取到 
            //第二个参数即是我们要缓存的数据 
            //第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
            $cache->set($cachePath, $cacheData, 60*60*12); 
        }

        return $data;
    }


    //慧学南通学校访问量
    public function visitAccount($province_id,$city_id,$area_id,$time,$type,$appid,$school_type,$msg_type)
    {
        $w = '';
        if(!empty($province_id))    $w['a.province_id'] = $province_id;
        if(!empty($city_id))        $w['a.city_id']     = $city_id;
        if(!empty($area_id))        $w['a.area_id']     = $area_id;
        if($school_type >= 0)       $w['a.school_type'] = $school_type;
        // 先从缓存里面读取数据，如果没有再从数据库获取
        $cache = Yii::$app->cache; 
        $cachePath = json_encode($w).$time.$type.$appid.$msg_type.'visitArea';
        $data = $cache->get($cachePath);
        // $cache->delete($cachePath);
        // $data = false;
        if ($data === false) {
            if(!empty($time))  {
                if($type == 3){
                    $time = functions::getMonthRangeByTime($time);
                }elseif($type == 2){
                    $time = functions::getSevenDaysRangeByTime($time);
                }elseif($type == 4){
                    $time = functions::getYearRangeByTime($time);
                }
               
                $stime = $time['stime'];
                $etime = $time['etime'];
            }else{
                $stime = '';
                $etime = '';
            }

            //获取天数
            if($type == 3) {
                $date_type = 'd';
                //月份的每一天
                $days = functions::getMonthDays(date('Y-m',$stime),$date_type);
            }elseif ($type == 2) {
                $date_type = 'd';
                //周的每一天
                $days = functions::getSevenDaysByTime($stime,$date_type);
            }elseif ($type == 4) {
                $date_type = 'm';
                //1年12个月
                $days = [];
                for ($i=0; $i < 12; $i++) { 
                    $days[$i]['date'] = strval($i+1);
                }
            }    


            //获取学校列表
            $qid_arr = [];
            $account = [];
            $account_list = Account::list($w);

            foreach ($account_list as $k => $v) {
                foreach ($v as $a => $school) {
                    $account[] = $school;
                    $qid_arr[] = intval($school['id']);
                }
            }

            $where['qid']       = $qid_arr;
            if(!empty($appid))  $where['jump_id'] = $appid;
            //访问列表
            $jump_list = QyJumpcontact::list($where,$stime,$etime);

            foreach ($account as $k => $v) {
                $visitInfo = [];
                foreach ($jump_list as $list) {
                    if($v['id'] == $list['qid']) $visitInfo[] = $list;    
                }

                $account[$k]['visitInfo'] = $visitInfo;

                unset($account[$k]['city_id']);
                unset($account[$k]['area_id']);
                unset($account[$k]['province_id']);
                unset($account[$k]['school_type']);
            }
            
            foreach ($account as $k => $school) {
               
                //每天访问总计
                foreach ($days as $a => $date) {
                    $visit_num = 0;

                    foreach ($school['visitInfo'] as $val) {
                        if($date['date'] == date($date_type,$val['create_time'])){
                            $visit_num ++;
                        }
                    }

                    $days[$a]['visit_num'] = $visit_num;
                }

                //去除
                unset($account[$k]['visitInfo']);

                //合计
                $new_days = [];
                $count_all = 0;
                foreach (array_values($days) as $a => $date) {
                    $new_days[('count'.$a)]  =  $date['visit_num'];
                    $count_all               += $date['visit_num'];
                }

                //返回31行
                $new_days = $this->thirty_days($new_days,count($new_days)-1,'count');

                $account[$k]['days'] = $new_days;
                $account[$k]['count_all'] = $count_all;
            }

            $data = $account;
            //这里我们可以操作数据库获取数据，然后通过$cache->set方法进行缓存 
            $cacheData = $data;
            //set方法的第一个参数是我们的数据对应的key值，方便我们获取到 
            //第二个参数即是我们要缓存的数据 
            //第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
            $cache->set($cachePath, $cacheData, 60*60*12); 
        }
        return $data;

    }


    //慧学南通地区访问量
    public function visitArea($province_id,$city_id,$area_id,$time,$type,$appid,$msg_type)
    {

        $w = '';
        if(!empty($province_id))    $w['a.province_id'] = $province_id;
        if(!empty($city_id))        $w['a.city_id']     = $city_id;
        if(!empty($area_id))        $w['a.area_id']     = $area_id;

        // 先从缓存里面读取数据，如果没有再从数据库获取
        $cache = Yii::$app->cache; 
        $cachePath = json_encode($w).$time.$type.$appid.$msg_type.'visitArea';
        $data = $cache->get($cachePath);
        // $cache->delete($cachePath);
        // $data = false;

        if ($data === false) {

            if(!empty($time))  {
                if($type == 3){
                    $time = functions::getMonthRangeByTime($time);
                }elseif($type == 2){
                    $time = functions::getSevenDaysRangeByTime($time);
                }elseif($type == 4){
                    $time = functions::getYearRangeByTime($time);
                }
               
                $stime = $time['stime'];
                $etime = $time['etime'];
            }else{
                $stime = '';
                $etime = '';
            }
            
            //获取天数
            if($type == 3) {
                $date_type = 'd';
                //月份的每一天
                $days = functions::getMonthDays(date('Y-m',$stime),$date_type);
            }elseif ($type == 2) {
                $date_type = 'd';
                //周的每一天
                $days = functions::getSevenDaysByTime($stime,$date_type);
            }elseif ($type == 4) {
                $date_type = 'm';
                //1年12个月
                $days = [];
                for ($i=0; $i < 12; $i++) { 
                    $days[$i]['date'] = strval($i+1);
                }
            }    

            //获取区
            $areas = ProvinceCityArea::areaList($province_id,$city_id,$area_id);

            //获取学校列表
            $account = Account::list($w);
            
            foreach ($areas as $k => $v) {
                $qid_arr = [];
                foreach ($account as $area_id => $school) {
                    if($v['id'] == $area_id){
                        $areas[$k]['area_name'] = $school[0]['area_name'];
                        foreach ($school as $school_info) {
                            $qid_arr[] = intval($school_info['id']);
                        }
                    }
                }

                $where['qid']       = $qid_arr;
                if(!empty($appid))  $where['jump_id'] = $appid;
                //访问列表
                $jump_list = QyJumpcontact::list($where,$stime,$etime);
                //每天访问总计
                foreach ($days as $a => $date) {
                    $visit_num = 0;
                    foreach ($jump_list as $val) {
                        if($date['date'] == date($date_type,$val['create_time'])){
                            $visit_num ++;
                        }
                    }

                    $days[$a]['visit_num'] = $visit_num;
                }

          
                //合计
                $new_days = [];
                $count_all = 0;
                foreach (array_values($days) as $a => $date) {
                    $new_days[('count'.$a)]  =  $date['visit_num'];
                    $count_all               += $date['visit_num'];
                }

                //返回31行
                $new_days = $this->thirty_days($new_days,count($new_days)-1,'count');

                $areas[$k]['days'] = $new_days;
                $areas[$k]['count_all'] = $count_all;


            }     

             //判断所有区是否都有学校,缺少area_name
            foreach ($areas as $k => $v) {
                if(empty($v['area_name']))  $areas[$k]['area_name'] = ProvinceCityArea::area_name($v['id']);
            }  

            $data = $areas;
            //这里我们可以操作数据库获取数据，然后通过$cache->set方法进行缓存 
            $cacheData = $data;
            //set方法的第一个参数是我们的数据对应的key值，方便我们获取到 
            //第二个参数即是我们要缓存的数据 
            //第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
            $cache->set($cachePath, $cacheData, 60*60*12); 
        }


        return $data;
    }




     /**
     * 读取发消息总量
     * @param $map string 查询条件
     * @param $send_count int 发消息总量
     */
    public function _get_send_wx_count($map, $send_count=0, $stime='', $etime='',$type=0)
    {
        $all_send_info = $this->get_wx_send_count($map,$stime,$etime);
        if($type == 1){
            if(!empty($all_send_info)){
                foreach($all_send_info as $v){
                    $appid = intval($v['appid']);
                    
                    switch($appid)
                    {
                        case 3:    // 家校圈
                        case 4:     // 即时通讯
                        case 11:    // 请假
                        case 12:    // 投票
                        case 13:    // 调研
                        case 14:    // 定向消息
                            $send_count += $v['sum'];
                            break;
                        case 6:// 通知暂时不加
                        case 7:// 作业暂时不加
                            break;
                        default :
                            $send_count += $v['sum'];
                            break;
                    }
                }
            }
             return $send_count;
        }else{
            return $all_send_info;
        }    
    }

    /**
     * 读取发微信消息数量
     * string $map 查询条件
     * by sherlock
     */
    public function get_wx_send_count($map, $stime='',$etime='')
    {
        if(empty($map)) return [];
        $appid = [3,11,12,13,14,6,7];

        $query = new Query();
        $info = $query
            //->select(["SUM(counts) as sum,appid,create_time,qid"])  
            ->select("counts,appid,create_time,qid")
            ->from(static::tableName())  
            ->where($map)  
            ->andFilterWhere(['between','create_time',$stime, $etime])
            ->andFilterWhere(['IN','appid',$appid])
            //->groupBy('appid')
            ->all();

        return empty($info) ? [] : $info;
    }

    //定义31天的数组
    public function thirty_days($res,$count,$string){

        if($count < 30){
            $res[$string.($count+1)] = '';
            return $this->thirty_days($res,($count+1),$string);     
        }else{
            return $res;
        }
       
    }



    //统计所有应用的消息发送
    public function getAppMsgList($appid,$qid_arr,$stime,$etime,$msg_type=''){

    	$where['qid'] 		 = $qid_arr;
    	$where['status'] = 1;
    	if($msg_type >= 0) $where['send_type']  = $msg_type;
		//作业统计 id=7
		if($appid > 0){
        	//作业统计 id=7
	        $work_count_res 	 = ($appid == 7)  ? Work::list($where,$stime,$etime) : [];    
	        //通知统计 id=6
	        $notice_count_res 	 = ($appid == 6)  ? Notice::list($where,$stime,$etime) : [];
	        //班级相册统计 id=10
	        $album_count_res  	 = ($appid == 10) ? Album::list($where,$stime,$etime) : [];
	        //家校圈统计 id=3
	        $community_count_res = ($appid == 3)  ? Community::list($where,$stime,$etime) : [];
	        //定向消息统计 id=14
	        $directmsg_count_res = ($appid == 14) ? NoticeDirect::list($where,$stime,$etime) : [];
	        //成绩统计 id=8
	        $score_count_res 	 = ($appid == 8)  ? ScoreExam::list($where,$stime,$etime) : [];
	        //调研统计 id=13
	        $survey_count_res 	 = ($appid == 13) ? Survey::list($where,$stime,$etime) : [];
	        //投票统计 id=12
	        $vote_count_res 	 = ($appid == 12) ? Vote::list($where,$stime,$etime) : [];
        }else{
        	//作业统计 id=7
	        $work_count_res 	 = Work::list($where,$stime,$etime);    
	        //通知统计 id=6
	        $notice_count_res 	 = Notice::list($where,$stime,$etime);
	        //班级相册统计 id=10
	        $album_count_res  	 = Album::list($where,$stime,$etime);
	        //家校圈统计 id=3
	        $community_count_res = Community::list($where,$stime,$etime);
	        //定向消息统计 id=14
	        $directmsg_count_res = NoticeDirect::list($where,$stime,$etime);
	        //成绩统计 id=8
	        $score_count_res 	 = ScoreExam::list($where,$stime,$etime);
	        //调研统计 id=13
	        $survey_count_res 	 = Survey::list($where,$stime,$etime);
	        //投票统计 id=12
	        $vote_count_res 	 = Vote::list($where,$stime,$etime);
        }

        $data['work_count_res']      = $work_count_res;
        $data['notice_count_res']    = $notice_count_res;
        $data['album_count_res']     = $album_count_res;
        $data['community_count_res'] = $community_count_res;
        $data['directmsg_count_res'] = $directmsg_count_res;
        $data['score_count_res']     = $score_count_res;
        $data['survey_count_res']    = $survey_count_res;
        $data['vote_count_res']      = $vote_count_res;

        return $data;
    }


    //读取缓存表
    public function getSchoolMsgCount($appid =0,$qid_arr,$stime,$etime){

        $where['qid'] = $qid_arr;
        $where['status'] = 1;
        if($appid > 0){
        	//作业统计 id=7
	        $work_count_res 	 = ($appid == 7)  ? StatisWork::counts($where,$stime,$etime) : [];    
	        //通知统计 id=6
	        $notice_count_res 	 = ($appid == 6)  ? StatisNotice::counts($where,$stime,$etime) : [];
	        //班级相册统计 id=10
	        $album_count_res  	 = ($appid == 10) ? StatisAlbum::counts($where,$stime,$etime) : [];
	        //家校圈统计 id=3
	        $community_count_res = ($appid == 3)  ? StatisCommunity::counts($where,$stime,$etime) : [];
	        //定向消息统计 id=14
	        $directmsg_count_res = ($appid == 14) ? StatisDirectmsg::counts($where,$stime,$etime) : [];
	        //成绩统计 id=8
	        $score_count_res 	 = ($appid == 8)  ? StatisScore::counts($where,$stime,$etime) : [];
	        //调研统计 id=13
	        $survey_count_res 	 = ($appid == 13) ? StatisSurvey::counts($where,$stime,$etime) : [];
	        //投票统计 id=12
	        $vote_count_res 	 = ($appid == 12) ? StatisVote::counts($where,$stime,$etime) : [];
        }else{
        	//作业统计 id=7
	        $work_count_res 	 = StatisWork::counts($where,$stime,$etime);    
	        //通知统计 id=6
	        $notice_count_res 	 = StatisNotice::counts($where,$stime,$etime);
	        //班级相册统计 id=10
	        $album_count_res  	 = StatisAlbum::counts($where,$stime,$etime);
	        //家校圈统计 id=3
	        $community_count_res = StatisCommunity::counts($where,$stime,$etime);
	        //定向消息统计 id=14
	        $directmsg_count_res = StatisDirectmsg::counts($where,$stime,$etime);
	        //成绩统计 id=8
	        $score_count_res 	 = StatisScore::counts($where,$stime,$etime);
	        //调研统计 id=13
	        $survey_count_res 	 = StatisSurvey::counts($where,$stime,$etime);
	        //投票统计 id=12
	        $vote_count_res 	 = StatisVote::counts($where,$stime,$etime);
        }

      


        $data['work_count_res']      = $work_count_res;
        $data['notice_count_res']    = $notice_count_res;
        $data['album_count_res']     = $album_count_res;
        $data['community_count_res'] = $community_count_res;
        $data['directmsg_count_res'] = $directmsg_count_res;
        $data['score_count_res']     = $score_count_res;
        $data['survey_count_res']    = $survey_count_res;
        $data['vote_count_res']      = $vote_count_res;

        return $data;
    }
}

