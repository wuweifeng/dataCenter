<?php

namespace api\controllers;
use Yii;
use yii\rest\Controller;
use yii\db\Query;
use api\models\WxMsgStatis;
use yii\web\Response;

class WxmsgstatisController extends Controller

{

    //  3:    家校圈
    //  4:    即时通讯
    //  6:    通知
    //  7:    作业
    //  11:   请假
    //  12:   投票
    //  13:   调研
    //  14:   定向消息
    
    public function beforeAction($action)
    {
        // $cache = Yii::$app->cache; 
        // $cache->flush();
       return Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    }

    //获取学校消息发送量
    public function actionMsgAccount()
    {
        $request     = Yii::$app->request;
        $WxMsgStatis = new WxMsgStatis();

        $province_id = $request->get('province_id','');
        $city_id     = $request->get('city_id',''); 
        $area_id     = $request->get('area_id','');
        $time        = $request->get('time',time());
        $type        = $request->get('type',3); 
        $appid       = $request->get('appid','');  
        $school_type = $request->get('school_type','');  
        $msg_type    = $request->get('msg_type',''); 

        $data = $WxMsgStatis->msgAccount($province_id,$city_id,$area_id,$time,$type,$appid,$school_type,$msg_type);

        return $data;
    }

    //获取地区消息发送量
    public function actionMsgArea()
    {
        $request     = Yii::$app->request;
        $WxMsgStatis = new WxMsgStatis();

        $province_id = $request->get('province_id','');
        $city_id     = $request->get('city_id',''); 
        $area_id     = $request->get('area_id','');
        $time        = $request->get('time',time());
        $type        = $request->get('type',3);  
        $appid       = $request->get('appid','');
        $msg_type    = $request->get('msg_type',''); 

        $data = $WxMsgStatis->msgArea($province_id,$city_id,$area_id,$time,$type,$appid,$msg_type);

        return $data;
    }

    //获取慧学南通学校访问量
    public function actionVisitAccount()
    {   

        $request     = Yii::$app->request;
        $WxMsgStatis = new WxMsgStatis();

        $province_id = $request->get('province_id','');
        $city_id     = $request->get('city_id',''); 
        $area_id     = $request->get('area_id','');
        $time        = $request->get('time',time());
        $type        = $request->get('type',3);  
        $appid       = $request->get('appid','');
        $school_type = $request->get('school_type','');  
        $msg_type    = $request->get('msg_type',''); 

        $data = $WxMsgStatis->visitAccount($province_id,$city_id,$area_id,$time,$type,$appid,$school_type,$msg_type);

        return $data;
    }

    //获取慧学南通地区访问量
    public function actionVisitArea()
    {

        $request     = Yii::$app->request;
        $WxMsgStatis = new WxMsgStatis();

        $province_id = $request->get('province_id','');
        $city_id     = $request->get('city_id',''); 
        $area_id     = $request->get('area_id','');
        $time        = $request->get('time',time());
        $type        = $request->get('type',3);  
        $appid       = $request->get('appid','');
        $msg_type    = $request->get('msg_type',''); 

        $data = $WxMsgStatis->visitArea($province_id,$city_id,$area_id,$time,$type,$appid,$msg_type);

        return $data;
    }
}