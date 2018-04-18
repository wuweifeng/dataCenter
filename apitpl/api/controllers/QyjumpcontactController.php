<?php

namespace api\controllers;
use Yii;
use yii\rest\Controller;
use yii\db\Query;
use api\models\QyJumpcontact;
use yii\web\Response;

class QyjumpcontactController extends Controller

{

    public function beforeAction($action)
    {
        // $cache = Yii::$app->cache; 
        // $cache->flush();
       return Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
    }

    public function actionList(){
      	
      	$QyJumpcontact = new QyJumpcontact();

      	$res = $QyJumpcontact->list();

      	return $res;
    
    }

}