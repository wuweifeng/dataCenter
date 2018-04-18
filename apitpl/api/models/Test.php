<?php
namespace api\models;

use Yii;
use yii\base\Model;
use yii\db\Query;
use yii\db\Expression;
use yii\db\session;
use common\helps\functions;

class Test extends Model
{

    /* 学校权限控制选项 bit:1-关闭,0-打开 */
    private static $_auth_option = array(
        'PARENT_JXQ_SEND' => 0,  //bit0 家长发送家校圈
        'PARENT_NOTICE_SEND' => 1,   //bit1 家长发送通知
        'PARENT_SURVEY_SEND' => 2,   //bit2 家长发送调研
        'PARENT_CONTACT_OTHER' => 3, //bit3 家长能否看到通讯中其他家长
        'PARENT_VOCATION_SEND' => 4, //bit4 家长不能使用请假
        'TEACHER_CONTACT_OTHER' => 5, //bit5 老师能否看到其他班级年级的成员
        'CONTACT_NOTJUST_MANAGER' => 6    //bit6 允许管理员和班主任更新通讯录结构或成员
    );

    //获取地区统计情况
    public function account_city($province_id,$city_id,$area_id,$level)
    {
        if($level != 1 && empty($province_id) && empty($city_id) && empty($area_id)){
            return ['status'=>1,'msg'=>'获取列表为空','lists'=>[]]; 
        }
        $w = '';
        if(!empty($province_id))    $w .= ' and a.`province_id`='.$province_id;
        if(!empty($city_id))        $w .= ' and a.`city_id`='.$city_id;
        if(!empty($area_id))        $w .= ' and a.`area_id`='.$area_id;

        // 先从缓存里面读取数据，如果没有再从数据库获取
        $cache = Yii::$app->cache; 
        $cachePath = $w.'account_city';
        $data = $cache->get($cachePath); 
        // $cache->delete($cachePath);
        // $data = false;
        if ($data === false) { 

            $query  = new Query();
            $result = $query->from('province_city_area')->select('name,id,sort')->all();
            $sql = "select a.`id`,a.`province_id`,a.`city_id`,a.`area_id`,a.`title`,a.`teacher_count`,a.`class_info`,s.`counts`,p.`sort`  from account as a,statis_school_expect as s,province_city_area as p where a.`area_id`= s.`areaid` and p.`id`= a.`area_id` ".$w;

            $res =  Yii::$app->db->createCommand($sql)->queryAll();
    
            $province_city_area= [];
            foreach ($result as $k => $v) {
                $province_city_area[$v['id']] = $v['name']; 
            }

            //地区分组
            $new_city_area = [];
            foreach ($res as $k => $v) {
                $v['area_name'] = $province_city_area[$v['province_id']].'-'.$province_city_area[$v['city_id']].'-'.$province_city_area[$v['area_id']];
                $new_city_area[$v['area_id']]['school_counts']  = intval($v['counts']); //学校总数
                $new_city_area[$v['area_id']]['schools'][]      = $v;
                $new_city_area[$v['area_id']]['sort']           = $v['sort'];     //区域排序
            }

            //全校数量
            foreach ($new_city_area as $k => $v) {
   
                $new_city_area[$k]['area_name'] = $v['schools'][0]['area_name'];
                $new_city_area[$k]['school_online_counts'] = count($v['schools']); //上线学校数

                //全区学校上线率
                $new_city_area[$k]['school_avg'] = $this->num_round((intval($v['school_counts']) > 0) ? (round(count($v['schools'])/intval($v['school_counts']),4)*100).'%' : '0.00%');

                $tea_counts = 0;        //教职工总人数
                $tea_online_counts = 0; //教职工导入人数
            
                $stu_counts = 0;        //学生总人数
                $stu_online_counts = 0; //学生导入人数

                $teas_focus = 0; //全区老师关注总数
                $stus_focus = 0; //全区学生关注总数
                foreach ($v['schools'] as $kk => $vv) {
                    $tea_stu_res = $query->from('contacts_member')->select('type,fstatus')->where(['qid'=>$vv['id'],'status'=>1])->all(); 
                    $teas =  $stus = $teas_focus_one = $stus_focus_one = 0;
                    $fstatus = '1,2,3';
                    foreach ($tea_stu_res as $val) {
                        if($val['type'] == 1)   $teas++;
                        if($val['type'] == 2)   $stus++;
                        if($val['type'] == 1 && strpos($fstatus,$val['fstatus']) !== false) $teas_focus_one++;
                        if($val['type'] == 2 && strpos($fstatus,$val['fstatus']) !== false) $stus_focus_one++;
                    }

                    //老师相关
                    $tea_counts += intval($vv['teacher_count']);    
                    $tea_online_counts += $teas;
                    $teas_focus += $teas_focus_one;

                    //学生相关
                    $stu_count = 0;
                    $class_info = json_decode($vv['class_info'], true);
                    empty($class_info) && $class_info = [];
           
                    foreach($class_info as $stype => $grades) {
                        foreach($grades as $year => $classes) {
                            foreach($classes as $sort => $stuCounts) {
                               $stu_count += $stuCounts; //所有学生
                            }
                        }
                    }

                    $stu_counts += $stu_count;
                    $stu_online_counts += $stus;
                    $stus_focus += $stus_focus_one;
                }

               
                $new_city_area[$k]['tea_online_counts'] = $tea_online_counts;   
                $new_city_area[$k]['tea_online_focus']  = intval($teas_focus);
                $new_city_area[$k]['tea_actual_count']  = $tea_counts;
                $check_tea_num                          = $this->check_num($tea_counts,$tea_online_counts,$teas_focus);
                $new_city_area[$k]['tea_counts']        = ($tea_counts == $check_tea_num && $check_tea_num > 0) ? strval($tea_counts) : $check_tea_num."/".$tea_counts;
                $tea_check_count                        = $this->check_count($tea_counts,$tea_online_counts);
                $new_city_area[$k]['teas_focus_avg']    = $this->num_round(($tea_check_count > 0) ? (round($teas_focus/$tea_check_count,4)*100).'%' : '0%');

                
                $new_city_area[$k]['stu_online_counts'] = $stu_online_counts;   
                $new_city_area[$k]['stu_online_focus']  = intval($stus_focus);
                $new_city_area[$k]['stu_actual_count']  = $stu_counts;
                $check_stu_num                          = $this->check_num($stu_counts,$stu_online_counts,$stus_focus);
                $new_city_area[$k]['stu_counts']        = ($stu_counts == $check_stu_num && $check_stu_num > 0) ? strval($stu_counts) : $check_stu_num."/".$stu_counts;
                $stu_check_count                        = $this->check_count($stu_counts,$stu_online_counts);
                $new_city_area[$k]['stus_focus_avg']    = $this->num_round(($stu_check_count > 0) ? (round($stus_focus/$stu_check_count,4)*100).'%' : '0%');

          
                //总关注率
                $member_focus           = intval($teas_focus) + intval($stus_focus);
                $member_online_counts   = $tea_online_counts + $stu_online_counts;
                $mem_check_count        = $this->check_count($member_focus,$member_online_counts);
                $new_city_area[$k]['member_focus_avg'] = $this->num_round(($mem_check_count > 0) ? (round($member_focus/$mem_check_count,4)*100).'%' : '0%');
                //导入总数
                $new_city_area[$k]['member_online_counts'] = $member_online_counts; 
                //关注总数
                $new_city_area[$k]['member_focus'] = $member_focus;
                //全校总数
                $check_tea_stu_num                  = $this->check_num($tea_counts+$stu_counts,$member_online_counts,$member_focus);
                $new_city_area[$k]['member_counts'] = (($tea_counts+$stu_counts) == $check_tea_stu_num && $check_tea_stu_num > 0) ? strval($tea_counts+$stu_counts) : $check_tea_stu_num."/".($tea_counts+$stu_counts);

            }

            //汇总
            $sum_area_count   = 0; //统计全区学校保有量
            $sum_area_online  = 0; //统计全区学校上线量
            $sum_area_avg     = 0; //统计全区学校上线率

            $sum_counts       = 0;//统计总导入全体人数（设置）
            $sum_tea_counts   = 0;//统计总导入全体教师实际人数（设置）
            $sum_stu_counts   = 0;//统计总导入全体学生实际人数（设置）

            $sum_count_online = 0;//统计总导入全体人数
            $sum_tea_online   = 0;//统计总导入全体教师人数
            $sum_stu_online   = 0;//统计总导入全体学生人数


            $sum_count_focus  = 0;//统计总导入关注人数
            $sum_tea_focus    = 0;//统计总导入老师关注人数
            $sum_stu_focus    = 0;//统计总导入学生关注人数

            $sum_count_avg    = 0;//统计总导入关注率
            $sum_tea_avg      = 0;//统计总导入老师关注率
            $sum_stu_avg      = 0;//统计总导入学生关注率


            foreach ($new_city_area as $k => $v) {

                $sum_area_count   += $v['school_counts'];
                $sum_area_online  += $v['school_online_counts'];

                 if($v['tea_counts'] > 0 && is_numeric($v['tea_counts'])) {
                    $sum_tea_counts += $v['tea_counts'];
                }elseif(strpos($v['tea_counts'],'录入中') !== false){
                    $sum_tea_counts += $v['tea_actual_count'];
                }

                if($v['stu_counts'] > 0 && is_numeric($v['stu_counts'])) {
                    $sum_stu_counts += $v['stu_counts'];
                }elseif(strpos($v['stu_counts'],'录入中') !== false){
                    $sum_stu_counts += $v['stu_actual_count'];
                }

                $sum_count_online += $v['member_online_counts'];
                $sum_count_focus  += $v['member_focus'];

                $sum_tea_online   += $v['tea_online_counts'];
                $sum_tea_focus    += $v['tea_online_focus']; 

                $sum_stu_online   += $v['stu_online_counts']; 
                $sum_stu_focus    += $v['stu_online_focus'];     
            }

            $sum_area_avg  = $this->num_round(($sum_area_count   > 0 ) ? (round($sum_area_online/$sum_area_count,4)*100)."%" : "0%");
            $sum_count_avg = $this->num_round(($sum_count_online > 0) ? (round($sum_count_focus/$sum_count_online,4)*100)."%" : "0%");
            $sum_tea_avg   = $this->num_round(($sum_tea_online   > 0) ? (round($sum_tea_focus/$sum_tea_online,4)*100)."%" : "0%"); 
            $sum_stu_avg   = $this->num_round(($sum_stu_online   > 0) ? (round($sum_stu_focus/$sum_stu_online,4)*100)."%" : "0%"); 


            $sum['sum_area_count']      = $sum_area_count;
            $sum['sum_area_online']     = $sum_area_online;
            $sum['sum_area_avg']        = $sum_area_avg;

            $sum['sum_counts']          = $sum_tea_counts + $sum_stu_counts;
            $sum['sum_count_online']    = $sum_count_online;
            $sum['sum_count_focus']     = $sum_tea_focus + $sum_stu_focus;
            $sum['sum_count_avg']       = $sum_count_avg;

            $sum['sum_tea_counts']      = $sum_tea_counts;
            $sum['sum_tea_online']      = $sum_tea_online;
            $sum['sum_tea_focus']       = $sum_tea_focus;
            $sum['sum_tea_avg']         = $sum_tea_avg;

            $sum['sum_stu_counts']      = $sum_stu_counts;
            $sum['sum_stu_online']      = $sum_stu_online;
            $sum['sum_stu_focus']       = $sum_stu_focus;
            $sum['sum_stu_avg']         = $sum_stu_avg;


    
            $data['data'] = functions::sortArrayMultiFields($new_city_area,['sort'=>SORT_ASC]);
            $data['sum']  = $sum;

            //这里我们可以操作数据库获取数据，然后通过$cache->set方法进行缓存 
            $cacheData = $data;
            //set方法的第一个参数是我们的数据对应的key值，方便我们获取到 
            //第二个参数即是我们要缓存的数据 
            //第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
            
           $cache->set($cachePath, $cacheData, 60*60*12); 
        }

        return  $data;
    } 

    //学校统计情况
    public function account_list($province_id,$city_id,$area_id,$level,$type=-1)
    {
        if($level != 1 && empty($province_id) && empty($city_id) && empty($area_id)){
            return ['status'=>1,'msg'=>'获取列表为空','lists'=>[]]; 
        }

        $w = '';
        if(!empty($province_id))    $w .= ' and a.`province_id`='.$province_id;
        if(!empty($city_id))        $w .= ' and a.`city_id`='.$city_id;
        if(!empty($area_id))        $w .= ' and a.`area_id`='.$area_id;

         // 先从缓存里面读取数据，如果没有再从数据库获取
        $cache = Yii::$app->cache; 
        $cachePath = $w.'account_list';
        $data = $cache->get($cachePath); 
        // $cache->delete($cachePath);
        // $data = false;
        if ($data === false) { 
            $query   = new Query(); 
            $result = $query->from('province_city_area')->select('name,id,sort')->all();

            $sql = "select a.`id`,a.`province_id`,a.`city_id`,a.`area_id`,a.`title`,a.`corpid`,a.`teacher_count`,a.`school_type`,a.`class_info`,a.`sort`,p.`sort` as psort  from account as a,province_city_area as p where a.`area_id`= p.`id` ".$w;
            ($type >= 0) && $sql .= ' and a.`school_type`='.$type; 

            $account =  Yii::$app->db->createCommand($sql)->queryAll();

            $schoolType = [ 0=>'其它',1=>'幼儿园',2=>'小学',3=>'初中',4=>'高中',5=>'大学' ];    
          
            $province_city_area= [];
            foreach ($result as $k => $v) {
                $province_city_area[$v['id']] = $v['name']; 
            }

            $new_account = [];
            foreach ($account as $k => $v) {
                $v['type_info'] = $schoolType[$v['school_type']];
                $v['area_name'] = $province_city_area[$v['province_id']].'-'.$province_city_area[$v['city_id']].'-'.$province_city_area[$v['area_id']];
                $new_account[$v['area_id']][] = $v;
            }

            $data = [];
            foreach ($new_account as $k => $v) {
                foreach ($v as $kk => $vv) {
                   $data[] = $vv;
                }
            }

            foreach ($data as $kk => $vv) {

                $tea_stu_res = $query->from('contacts_member')->select('type,fstatus')->where(['qid'=>$vv['id'],'status'=>1])->all(); 

                $teas =  $stus = $teas_focus_one = $stus_focus_one = 0;
                $fstatus = '1,2,3';
                foreach ($tea_stu_res as $val) {
                    if($val['type'] == 1)   $teas++;
                    if($val['type'] == 2)   $stus++;
                    if($val['type'] == 1 && strpos($fstatus,$val['fstatus']) !== false) $teas_focus_one++;
                    if($val['type'] == 2 && strpos($fstatus,$val['fstatus']) !== false) $stus_focus_one++;
                }

                //老师相关
                $tea_counts = intval($vv['teacher_count']);    
                $tea_online_counts = $teas;
                $teas_focus = $teas_focus_one;

                //学生相关
                $stu_counts = 0;
                $class_info = json_decode($vv['class_info'], true);
                empty($class_info) && $class_info = [];
                
                foreach($class_info as $stype => $grades) {
                    foreach($grades as $year => $classes) {
                        foreach($classes as $sort => $stuCounts) {
                           $stu_counts += $stuCounts; //所有学生
                        }
                    }
                }
                
                $stu_online_counts = $stus;
                $stus_focus = $stus_focus_one;

                $data[$kk]['tea_online_counts'] = $tea_online_counts;   
                $data[$kk]['tea_online_focus']  = intval($teas_focus);
                $data[$kk]['tea_actual_count']  = $tea_counts;
                $check_tea_num                  = $this->check_num($tea_counts,$tea_online_counts,$teas_focus);
                $data[$kk]['tea_counts']        = ($tea_counts == intval($check_tea_num) && $check_tea_num >0) ? strval($tea_counts) : $check_tea_num."/".$tea_counts;
                $tea_check_count                = $this->check_count($tea_counts,$tea_online_counts);
                $data[$kk]['teas_focus_avg']    = $this->num_round(($tea_check_count > 0) ? (round($teas_focus/$tea_check_count,4)*100).'%' : '0%');

              
                $data[$kk]['stu_online_counts'] = $stu_online_counts;   
                $data[$kk]['stu_online_focus']  = intval($stus_focus);
                $data[$kk]['stu_actual_count']  = $stu_counts;
                $check_stu_num                  = $this->check_num($stu_counts,$stu_online_counts,$stus_focus);
                $data[$kk]['stu_counts']        = ($stu_counts == intval($check_stu_num) && $check_stu_num >0) ? strval($stu_counts) : $check_stu_num."/".$stu_counts;
                $stu_check_count                = $this->check_count($stu_counts,$stu_online_counts); 
                $data[$kk]['stus_focus_avg']    = $this->num_round(($stu_check_count > 0) ? (round($stus_focus/$stu_check_count,4)*100).'%' : '0%');

               
                $member_focus = intval($teas_focus) + intval($stus_focus);
                $member_online_counts           = $tea_online_counts + $stu_online_counts;
                $mem_check_count                = $this->check_count($tea_counts+$stu_counts,$member_online_counts); 
                $data[$kk]['member_focus_avg']  = $this->num_round(($mem_check_count > 0) ? (round($member_focus/$mem_check_count,4)*100).'%' : '0%');
                //导入总数
                $data[$kk]['member_online_counts'] = $member_online_counts; 
                //关注总数
                $data[$kk]['member_focus']      = $member_focus;
                //全校总数
                $check_tea_stu_num              = $this->check_num($tea_counts+$stu_counts,$member_online_counts,$member_focus);
                $data[$kk]['member_counts']      = (($tea_counts+$stu_counts) == intval($check_tea_stu_num) && $check_tea_stu_num >0) ? strval($tea_counts+$stu_counts) : $check_tea_stu_num."/".($tea_counts+$stu_counts);
            }

             //汇总
            $sum_counts       = 0;//统计总导入全体人数（设置）
            $sum_tea_counts   = 0;//统计总导入全体教师实际人数（设置）
            $sum_stu_counts   = 0;//统计总导入全体学生实际人数（设置）

            $sum_count_online = 0;//统计总导入全体人数
            $sum_tea_online   = 0;//统计总导入全体教师人数
            $sum_stu_online   = 0;//统计总导入全体学生人数

            $sum_count_focus  = 0;//统计总导入关注人数
            $sum_tea_focus    = 0;//统计总导入老师关注人数
            $sum_stu_focus    = 0;//统计总导入学生关注人数

            $sum_count_avg    = 0;//统计总导入关注率
            $sum_tea_avg      = 0;//统计总导入老师关注率
            $sum_stu_avg      = 0;//统计总导入学生关注率


            foreach ($data as $k => $v) {

                if($v['tea_counts'] > 0 ) {
                    $sum_tea_counts += $v['tea_counts'];
                }elseif(strpos($v['tea_counts'],'录入中') !== false){
                    $sum_tea_counts += $v['tea_actual_count'];
                }


                if($v['stu_counts'] > 0) {
                    $sum_stu_counts += $v['stu_counts'];
                }elseif(strpos($v['stu_counts'],'录入中') !== false){
                    $sum_stu_counts += $v['stu_actual_count'];
                }

                $sum_count_online += $v['member_online_counts'];
                $sum_count_focus  += $v['member_focus'];

                $sum_tea_online   += $v['tea_online_counts'];
                $sum_tea_focus    += $v['tea_online_focus']; 

                $sum_stu_online   += $v['stu_online_counts']; 
                $sum_stu_focus    += $v['stu_online_focus'];     
            }

            $sum_count_avg = $this->num_round(($sum_count_online > 0) ? (round($sum_count_focus/$sum_count_online,4)*100)."%" : "0%");
            $sum_tea_avg   = $this->num_round(($sum_tea_online   > 0) ? (round($sum_tea_focus/$sum_tea_online,4)*100)."%" : "0%"); 
            $sum_stu_avg   = $this->num_round(($sum_stu_online   > 0) ? (round($sum_stu_focus/$sum_stu_online,4)*100)."%" : "0%"); 


            $sum['sum_counts']          = $sum_tea_counts + $sum_stu_counts;
            $sum['sum_count_online']    = $sum_count_online;
            $sum['sum_count_focus']     = $sum_tea_focus + $sum_stu_focus;
            $sum['sum_count_avg']       = $sum_count_avg;

            $sum['sum_tea_counts']      = $sum_tea_counts;
            $sum['sum_tea_online']      = $sum_tea_online;
            $sum['sum_tea_focus']       = $sum_tea_focus;
            $sum['sum_tea_avg']         = $sum_tea_avg;

            $sum['sum_stu_counts']      = $sum_stu_counts;
            $sum['sum_stu_online']      = $sum_stu_online;
            $sum['sum_stu_focus']       = $sum_stu_focus;
            $sum['sum_stu_avg']         = $sum_stu_avg;

            $data['data'] = functions::sortArrayMultiFields($data,['psort' => SORT_ASC, 'sort' => SORT_ASC]);

            $data['sum']  = $sum;
            //这里我们可以操作数据库获取数据，然后通过$cache->set方法进行缓存 
            $cacheData = $data;
            //set方法的第一个参数是我们的数据对应的key值，方便我们获取到 
            //第二个参数即是我们要缓存的数据 
            //第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
            
           $cache->set($cachePath, $cacheData, 60*60*12); 

        }
        return  $data;
    }

   // 班级统计情况
    public function account_class($province_id,$city_id,$area_id,$school_id,$level)
    {

        if($level != 1 && empty($province_id) && empty($city_id) && empty($area_id)){
            return ['status'=>1,'msg'=>'获取列表为空','lists'=>[]]; 
        } 

        // $extend & 4095 入学年份
        // $extend >> 12 学校类型
        $schoolType = [ 0=>'其它',1=>'幼儿园',2=>'小学',3=>'初中',4=>'高中',5=>'大学' ];   
    
        $w = '';
        if(!empty($province_id))    $w['province_id']  = $province_id;
        if(!empty($city_id))        $w['city_id']      = $city_id;
        if(!empty($area_id))        $w['area_id']      = $area_id;
        if(!empty($school_id))      $w['id']           = $school_id;

        // 先从缓存里面读取数据，如果没有再从数据库获取
        $cache = Yii::$app->cache; 
        if(is_array($w)) {
            $cachePath = implode('', $w).'account_class';
        }else{
            $cachePath = 'account_class';
        }
       
        $data = $cache->get($cachePath); 
        
        if ($data === false) { 
            $query   = new Query();
            // 查询出省市区对应的id和名称
            $result = $query->from('province_city_area')->select('name,id')->all();
            $province_city_area = [];
            foreach ($result as $value) {
                $province_city_area[$value['id']] = $value['name'];
            }

            // 查询所有的学校信息
            $account =  $query 
                ->from('account')
                ->select('id,area_id,city_id,province_id,title,teacher_count,school_type,class_info')
                ->where($w)
                ->all();

        
            $new_account = [];
            foreach ($account as $k => $v) {
                $v['type_info'] = $schoolType[$v['school_type']];
                $v['area_name'] = $province_city_area[$v['province_id']].'-'.$province_city_area[$v['city_id']].'-'.$province_city_area[$v['area_id']];
                $new_account[$v['area_id']][] = $v;
            }

            unset($result);
            unset($account);

            $data = [];
            foreach ($new_account as $k => $v) {
                foreach ($v as $kk => $vv) {
                   $data[] = $vv;
                }
            }

            //汇总
            $sum_stu_online   = 0;//统计总导入全体学生人数
            $sum_stu_focus    = 0;//统计总导入学生关注人数
            $sum_stu_avg      = 0;//统计总导入学生关注率

            $schools = []; 
            //获取年级
            foreach ($data as $k => $v) {
                $class_info = json_decode($v['class_info'],true);
                empty($class_info) && $class_info = [];

                $h = 0; //去重
                $sql = 'select g.`title`,c.`extend` as year,g.`extend`,g.`id`,cg.`type`,cg.`title` as school_child,c.`title` as grade from contacts_group as c,contacts_group as g,contacts_group as cg where cg.`id`=c.`pid` and c.`id`=g.`pid` and g.`qid`='.$v['id'].' and g.`type`=6 and g.`status`=1 order by c.`extend`>>12 asc,c.`extend`&4095 desc,g.`extend` asc';
                $classes = Yii::$app->db->createCommand($sql)->queryAll();
                $stu_unique = []; //学生去重

                foreach ($classes as $key => $val) {
                    //$classes[$key]['year']          = strval($val['year'] & 4095);
                    $classes[$key]['area_name']     = $v['area_name'];
                    $classes[$key]['area_id']       = $v['area_id'];
                    $classes[$key]['type_info']     = $v['type_info'];
                    $classes[$key]['school_title']  = $v['title'];
                    if ($val['type'] == 4) {
                    	$classes[$key]['grade']  = $val['school_child'].' / '.$val['grade'];
                    }
                    
                    $stu_counts = 0;
                    //获取班级设置人数
                    foreach($class_info as $stype => $grades) {
                        foreach($grades as $year => $class) {
                            if($year == strval($val['year'] & 4095)){
                                foreach($class as $sort => $stuCounts) {
                                    if($sort == $val['extend']){
                                        $stu_counts = strval($stuCounts);

                                    }
                                }
                            }
                            
                        }
                    }
                    
                    

                    //筛选属于这个班级的成员数量
                    $stu_online_count_res = $query->from('contacts_group_link')->select('memid')->distinct('memid')->where(['qid'=>$v['id'],'groupid'=>$val['id'],'mtype'=>1])->all();
                   
                    $stu_online_counts = count($stu_online_count_res);
                    $classes[$key]['stu_online_counts'] = intval($stu_online_counts);


                    $sql = 'select count(m.`id`) as count from contacts_member m, contacts_group_link g where m.`id`=g.`memid` and g.`qid`='.$v['id'].' and g.`groupid`='.$val['id'].' and g.`mtype`=1 and m.`status`=1 and m.`fstatus`>0 and m.`type`=2';
                    $stu_online_focus = Yii::$app->db->createCommand($sql)->queryAll()[0]['count'];

                    $classes[$key]['stu_online_focus'] = intval($stu_online_focus);

                    $check_stu_num                  = $this->check_num($stu_counts,$stu_online_counts,$stu_online_focus);
                    $classes[$key]['stu_counts']        = ($stu_counts == intval($check_stu_num) && $check_stu_num >0) ? strval($stu_counts) : $check_stu_num."/".$stu_counts;
                    $stu_check_count = $this->check_count($stu_counts,intval($stu_online_counts));
                    $classes[$key]['stus_focus_avg'] = $this->num_round(($stu_check_count > 0) ? (round($stu_online_focus/$stu_check_count,4)*100).'%' : '0%');

                    foreach ($stu_online_count_res as $b) {
                        if(!in_array($b['memid'],$stu_unique)){
                            $stu_unique[] = $b['memid'];
                        }else{
                            $h ++;
                        }
                    }
                    if($key == (count($classes)-1)){
                        $classes[$key]['unique_num'] = $h;
                    }

                    $sum_stu_online += ($stu_online_counts - $h);
                    $sum_stu_focus  += $stu_online_focus;
                }
                      
                $schools[] = $classes; 
            }


            // 重新排列班级
            $data = [];
            foreach ($schools as $v) {
                foreach ($v as $vv) {
                   $data[] = $vv;
                } 
            }


            $data['data'] = $data;

            $sum_stu_avg = $this->num_round(($sum_stu_online > 0) ? (round($sum_stu_focus/$sum_stu_online,4)*100).'%' : '0%');
            $sum['sum_stu_online']      = $sum_stu_online;
            $sum['sum_stu_focus']       = $sum_stu_focus;
            $sum['sum_stu_avg']         = $sum_stu_avg;

            $data['sum'] = $sum;
            //这里我们可以操作数据库获取数据，然后通过$cache->set方法进行缓存 
            $cacheData = $data;
            //set方法的第一个参数是我们的数据对应的key值，方便我们获取到 
            //第二个参数即是我们要缓存的数据 
            //第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
            
           $cache->set($cachePath, $cacheData, 60*60*12); 
        } 

        return $data;
    }

    //获取区域
    public function area_list()
    {

        $query     = new Query();
        $res =  $query ->from('province_city_area')->select('id,name,type')->all();

        $data           = [];
        $province       = [];
        $province_city  = [];
        $city_area      = [];

        //转换格式：id:name
        foreach ($res as $k => $v) {
            if($v['type'] == 1){
                $province['1'][$v['id']] = $v['name'];
                $city_res = $query ->from('province_city_area')->select('id,name,type')->where(['type'=>2,'pid'=>$v['id']])->all();
                foreach ($city_res as $kk => $vv) {
                   $province_city[$v['id']][$vv['id']] = $vv['name']; 
                }    
            }   

            if($v['type'] == 2){
                $city[$v['id']] = $v['name'];
                $area_res = $query ->from('province_city_area')->select('id,name,type')->where(['type'=>3,'pid'=>$v['id']])->all();
                foreach ($area_res as $kk => $vv) {
                   $city_area[$v['id']][$vv['id']] = $vv['name']; 
                }    
            }   

        } 
        
        //拼接
        foreach ($province as $k => $v)         { $data[$k] = $v; }
        foreach ($province_city as $k => $v)    { $data[$k] = $v; }
        foreach ($city_area as $k => $v)        { $data[$k] = $v; }


        return ['status'=>1,'msg'=>'获取成功','res'=>$data];
    }


    //账号登录
    public function login($user_name,$password)
    {
        if(empty($user_name)) return ['status'=>0,'msg'=>'用户名不能为空'];
        if(empty($password))  return ['status'=>0,'msg'=>'密码不能为空'];

        $query = new Query();
        $user =  $query->from('data_center_user')->where(['user_name'=>$user_name])->one();

        if(empty($user)) {
            return ['status'=>0,'msg'=>'用户名不存在'];
        }else{
            $w = ['user_name'=>$user_name,'password'=>$password];
            $user_info =  $query->from('data_center_user')->where($w)->one();
            if(empty($user_info)) {
                return ['status'=>0,'msg'=>'密码有误'];
            }else{
                $session = \Yii::$app->session;
                $session->set('USER' , $user_info);

                $user_zone = $this->user_zone($user_info['id'],1);
                return ['status'=>1,'msg'=>'登录成功','res'=>$user_info,'user_zone'=>$user_zone];
            }
        }
    }

    /*退出登录*/
    public function logout(){
        $session = \Yii::$app->session;
        $res = $session->remove('USER');

        return ['status'=>1,'msg'=>'退出登录成功'];
    }

    /*
     *$user['level'] 等级: 1为全国，2为省，3为市，4为区
     *$static：空为前端调用接口，1为后台调用   
     *$type：空为PC端调用，1为企业微信调用
     */

    //通过用户登录返回 对应区域列表
    public function user_zone($user_id,$static='',$type=''){
        $query = new Query();

        if(empty($type)){
            $user  = $query->from('data_center_user')->where(['id'=>$user_id])->one();    
            if(empty($user))  return ['status'=>0,'msg'=>'用户不存在'];
            //获取属于用户查看的省份
            $user_zone = $query->from('data_user_zone')->select('province_id,city_id,area_id')->where(['uid'=>$user['id'],'status'=>1])->one();
        }elseif($type > 1){ //企业微信进入
            $user_zone = $query->from('ucenter_member')->select('province_id,city_id,area_id,type,name,id')->where(['id'=>$user_id])->one();    

            if($user_zone['type'] == 3){ //省管理员
                if(empty($user_zone['province_id'])){
                    if(empty($user_zone['city_id'])){
                        //通过区id查找省id
                        $zone_area = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['area_id']])->one();  
                        $user_zone['city_id'] = $zone_area['pid'];
                        $zone_city = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['city_id']])->one();  
                        $user_zone['province_id'] = $zone_city['pid'];

                    }else{
                        $zone_city = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['city_id']])->one();  
                        $user_zone['province_id'] = $zone_city['pid'];
                    }
                }
            }elseif($user_zone['type'] == 4){
                if(empty($user_zone['city_id'])){
                    //通过区id查找省id
                    $zone_area = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['area_id']])->one();  
                    $user_zone['city_id'] = $zone_area['pid'];
                    $zone_city = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['city_id']])->one();  
                    $user_zone['province_id'] = $zone_city['pid'];

                }else{
                    $zone_city = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['city_id']])->one();  
                    $user_zone['province_id'] = $zone_city['pid'];
                }
            }
            elseif($user_zone['type'] == 5){
                if(empty($user_zone['province_id'])){
                    if(empty($user_zone['city_id'])){
                        //通过区id查找省id
                        $zone_area = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['area_id']])->one();  
                        $user_zone['city_id'] = $zone_area['pid'];
                        $zone_city = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['city_id']])->one();  
                        $user_zone['province_id'] = $zone_city['pid'];

                    }else{
                        $zone_city = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['city_id']])->one();  
                        $user_zone['province_id'] = $zone_city['pid'];
                    }
                }
                else{
                    if(empty($user_zone['city_id'])){
                        //通过区id查找省id
                        $zone_area = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['area_id']])->one();  
                        $user_zone['city_id'] = $zone_area['pid'];
                        $zone_city = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['city_id']])->one();  
                        $user_zone['province_id'] = $zone_city['pid'];

                    }else{
                        $zone_city = $query->from('province_city_area')->select('pid')->where(['id'=>$user_zone['city_id']])->one();  
                        $user_zone['province_id'] = $zone_city['pid'];
                    }
                }
            }


            $user['level']  = $user_zone['type'] - 1;
            $user['name']   = $user_zone['name'];
            $user['id']     = $user_zone['id'];
        }

        $data           = [];
        $province       = [];
        $province_city  = [];
        $city_area      = [];

        if($user['level'] < 3 ){
            //省
            if($user['level'] == 1){
                $res =  $query->from('province_city_area')->select('id,name,type')->where(['type'=>1])->all();    
            }elseif($user['level'] == 2){
                $res[] = $query->from('province_city_area')->select('id,name,type')->where(['id'=>$user_zone['province_id']])->one();
            }

            if(!empty($res)){
                //转换格式：id:name
                foreach ($res as $k => $v) {
                    if($v['type'] == 1){
                        $province['1'][$v['id']] = $v['name'];
                        $city_res = $query->from('province_city_area')->select('id,name,type')->where(['type'=>2,'pid'=>$v['id']])->all();
                        foreach ($city_res as $kk => $vv) {
                            $province_city[$v['id']][$vv['id']] = $vv['name'];
                            $area_res = $query->from('province_city_area')->select('id,name,type')->where(['type'=>3,'pid'=>$vv['id']])->all();
                            foreach ($area_res as $a => $b) {
                               $city_area[$vv['id']][$b['id']] = $b['name']; 
                            }    
                        }    
                    }        
                } 
            }
        }elseif($user['level'] == 3){
            //市
            $res[] = $query->from('province_city_area')->select('id,name,type')->where(['id'=>$user_zone['province_id']])->one();
            if(!empty($res)){
                foreach ($res as $k => $v) {
                    $province['1'][$v['id']] = $v['name'];
                    $city_res[] = $query->from('province_city_area')->select('id,name,type')->where(['id'=>$user_zone['city_id']])->one();
                    foreach ($city_res as $kk => $vv) {
                        $province_city[$v['id']][$vv['id']] = $vv['name'];
                        $area_res = $query->from('province_city_area')->select('id,name,type')->where(['type'=>3,'pid'=>$vv['id']])->all();
                        foreach ($area_res as $a => $b) {
                           $city_area[$vv['id']][$b['id']] = $b['name']; 
                        }    
                    }  
                }
            }
            
        }else{
            //区
            $res[] = $query->from('province_city_area')->select('id,name,type')->where(['id'=>$user_zone['province_id']])->one();
            if(!empty($res)){
                foreach ($res as $k => $v) {

                    $province['1'][$v['id']] = $v['name'];
                    $city_res[] = $query->from('province_city_area')->select('id,name,type')->where(['id'=>$user_zone['city_id']])->one();
                    foreach ($city_res as $kk => $vv) {
                        $province_city[$v['id']][$vv['id']] = $vv['name']; 
                        $area_res = $query->from('province_city_area')->select('id,name,type')->where(['id'=>$user_zone['area_id']])->all();
                       foreach ($area_res as $a => $b) {
                           $city_area[$vv['id']][$b['id']] = $b['name']; 
                        }     
                    }             
                }
            }
        }

      

        //拼接
        foreach ($province as $k => $v)      {$data[$k] = $v;}
        foreach ($province_city as $k => $v) {$data[$k] = $v;}
        foreach ($city_area as $k => $v)     {$data[$k] = $v;}


        if($static == 1){
            return $data;
        }else{
            return ['status'=>1,'msg'=>'获取成功','res'=>$data,'user'=>$user];
        }
       
    }

    /*
        实际人数已设置 关注率=关注人数/实际人数*100%
        实际人数未设置 关注率=关注人数/导入人数*100%
     */
    public function check_count($count1,$count2){
        if($count1 == $count2){
            return $count1;
        }else{
            if($count1 > $count2){
                return $count1;
            }else{
                return $count2;
            }
        } 
    }

    /*修改密码
    *user_id：用户ID
    *user_name：用户名
    *prepwd：原密码
    *pwd：新密码
    *repwd：确认新密码
   */
    public function modify_pwd($user_id,$user_name,$prepwd,$pwd,$repwd)
    {
        if(empty($user_id))     return ['status'=>0,'msg'=>'隐藏用户ID不能为空'];
        if(empty($user_name))   return ['status'=>0,'msg'=>'隐藏用户名不能为空'];
        if(empty($prepwd))      return ['status'=>0,'msg'=>'原密码不能为空'];
        if(empty($pwd))         return ['status'=>0,'msg'=>'新密码不能为空'];
        if(empty($repwd))       return ['status'=>0,'msg'=>'确认新密码不能为空'];

        if($pwd !== $repwd)     return ['status'=>0,'msg'=>'新密码和确认新密码不一致'];
        $query = new Query();

        $user = $query->from('data_center_user')->where(['id'=>$user_id,'user_name'=>$user_name,'status'=>1])->one();
        if($user['password'] !== $prepwd)   return ['status'=>0,'msg'=>'原密码输出错误'];
        
        if(empty($user)) return ['status'=>0,'msg'=>'用户不存在'];

        $res = Yii::$app->db->createCommand()->update('data_center_user',['password'=> $pwd], 'id='.$user_id)->execute(); 

        if($res !== false) {
            return ['status'=>1,'msg'=>'密码修改成功'];
        }else{
            return ['status'=>0,'msg'=>'密码修改失败'];
        }
    }

    //判断实际人数和导入人数，关注率
    public function check_num($counts,$online_counts,$focus_counts)
    {
        if($counts == 0){
            $data = '未填';
        }else{
            $data = ($counts >= $online_counts) ? $counts : '录入中' ;
        }
              
        return $data;

    }
    

    //学校权限
    public function account_config($province_id,$city_id,$area_id,$level)
    {
        if($level != 1 && empty($province_id) && empty($city_id) && empty($area_id)){
            return ['status'=>1,'msg'=>'获取列表为空','lists'=>[]]; 
        }

        $w = '';
        if(!empty($province_id))    $w .= ' and a.`province_id`='.$province_id;
        if(!empty($city_id))        $w .= ' and a.`city_id`='.$city_id;
        if(!empty($area_id))        $w .= ' and a.`area_id`='.$area_id;

        // 先从缓存里面读取数据，如果没有再从数据库获取
        $cache = Yii::$app->cache; 
        $cachePath = $w.'account_config';
        $data = $cache->get($cachePath); 
       
        if ($data === false) { 

            $query   = new Query(); 
            $result = $query->from('province_city_area')->select('name,id,sort')->all();

             $sql = "select a.`id`,a.`province_id`,a.`city_id`,a.`area_id`,a.`title`,p.`sort` from account as a,province_city_area as p where a.`area_id`= p.`id` ".$w;

            $account =  Yii::$app->db->createCommand($sql)->queryAll();

            $province_city_area= [];
            foreach ($result as $k => $v) {
                $province_city_area[$v['id']] = $v['name']; 
            }

            $new_account = [];
            foreach ($account as $k => $v) {
                $v['area_name'] = $province_city_area[$v['province_id']].'-'.$province_city_area[$v['city_id']].'-'.$province_city_area[$v['area_id']];

                $config = $this->config_detail($v['id']);
                $v['CONTACT_NOTJUST_MANAGER']   = $config['CONTACT_NOTJUST_MANAGER'];
                $v['PARENT_CONTACT_OTHER']      = $config['PARENT_CONTACT_OTHER'];
                $v['PARENT_JXQ_SEND']           = $config['PARENT_JXQ_SEND'];
                $v['PARENT_NOTICE_SEND']        = $config['PARENT_NOTICE_SEND'];
                $v['PARENT_SURVEY_SEND']        = $config['PARENT_SURVEY_SEND'];
                $v['PARENT_VOCATION_SEND']      = $config['PARENT_VOCATION_SEND'];
                $v['TEACHER_CONTACT_OTHER']     = $config['TEACHER_CONTACT_OTHER'];

                $new_account[$v['area_id']][]   = $v;

            }


            $data = [];
            foreach ($new_account as $k => $v) {
                foreach ($v as $kk => $vv) {
                   $data[] = $vv;
                }
            }

            $data = functions::sortArrayMultiFields($data,['sort'=>SORT_ASC]);

            //这里我们可以操作数据库获取数据，然后通过$cache->set方法进行缓存 
            $cacheData = $data;
            //set方法的第一个参数是我们的数据对应的key值，方便我们获取到 
            //第二个参数即是我们要缓存的数据 
            //第三个参数是缓存时间，如果是0，意味着永久缓存。默认是0 
            
           $cache->set($cachePath, $cacheData, 60*60*12); 

        }

        return $data;
    }

    /** 缓存读取校园号权限配置信息
     * @param int $qid 校园号ID
     * @param bool $bflush 是否更新缓存
    */
    public function config_detail($qid, $bflush=false)
    {
        $detail = $this->detail_cache($qid);
        if(! empty($detail)) {
            $auth_cfg = intval($detail['auth_config']);
            foreach(self::$_auth_option as $op => $opbit) {
                $opbit = intval($opbit);
                $data[$op] = ($auth_cfg & (1 << $opbit)) ? 0 : 1;
            }
        }
        
        return (isset($data) && !empty($data)) ? $data : [];
    }

    /** 缓存读取校园号信息
     * @param int $qid 校园号ID
     * @param bool $bflush 是否更新缓存
    */
    public function detail_cache($qid, $bflush=false)
    {
        $qid = intval($qid);
        if($qid > 0) {  
            $query   = new Query(); 
            $_data = $query->from('account')->where(['id'=>$qid])->one();
            if(! empty($_data)) {
                $data = array(
                    'id' => $_data['id'], 
                    'cuid' => $_data['cuid'], 
                    'title' => $_data['title'], 
                    'corpid' => $_data['corpid'], 
                    'province_id' => $_data['province_id'], 
                    'city_id' => $_data['city_id'], 
                    'area_id' => $_data['area_id'], 
                    'school_type' => $_data['school_type'], 
                    'auth_config' => $_data['auth_config']
                );  
            }   
        }
        
        return (isset($data) && !empty($data)) ? $data : [];
    }


    /**
     * Sort array by filed and type, common utility method.
     * @param array $array
     * @param string $filed1
     * @param string $type1 SORT_ASC or SORT_DESC
     * @param string $filed2
     * @param string $type2 SORT_ASC or SORT_DESC
     */
    public function sortByTwoFiled($data, $filed1, $type1, $filed2, $type2)
    {
        if (count($data) <= 0) {
            return $data;
        }
        foreach ($data as $key => $value) {
            $temp_array1[$key] = $value[$filed1];
            $temp_array2[$key] = $value[$filed2];
        }
        array_multisort($temp_array1, $type1, $temp_array2, $type2, $data);
        return $data;
    }

    //保留两位小数，不管是整数还是其它
    public function num_round($str){
        $num = str_replace('%','',$str); 
        if(strpos($num,'.') == false){
            return $num.'.00%';
        }else{
            $arr = explode('.',$num);
            if(strlen($arr[1]) < 2){
                return $num = $arr[0].'.'.$arr[1].'0%';
            }else{
                return $num.'%';
            } 
        }
    }



}
