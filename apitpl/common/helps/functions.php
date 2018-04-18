<?php
namespace common\helps;

/*
 * 自定义全局公共方法
 */
class functions{

	//排序
	public static function array_multi_sort($arrays, $sort_key, $sort_order = SORT_ASC, $sort_type = SORT_NUMERIC )
	{
	    if(is_array($arrays))
	    {
	        foreach ($arrays as $array)
	        {
	            if(is_array($array))
	            {
	                $key_arrays[] = $array[$sort_key];
	            }
	            else
	            {
	                return false;
	            }
	        }
	    }
	    else
	    {
	        return false;
	    }

	    array_multisort($key_arrays,$sort_order,$sort_type,$arrays);
	    return $arrays;
	}


	//清除缓存
	public static function clearCache($cachePath=[])
    {
        $cachePath = is_array($cachePath) ? $cachePath : [$cachePath];
        $cache = \Yii::$app->cache;
        if (empty($cachePath)) {
            $cache->flush();
        } else {
            foreach ($cachePath as $value) {
                $cache->delete($value);
            }
        }
    }

    /**
	 * Sort multi array by filed and type.
	 * @param data $array
	 * @param condition $array
	 *  sortArrayMultiFields($data, ['score' => SORT_DESC])
		sortArrayMultiFields($data, ['score' => SORT_DESC, 'count' => SORT_ASC])
		sortArrayMultiFields($data, ['score' => SORT_DESC, 'count' => SORT_ASC, 'name' => SORT_ASC])
		字段数量可自定义
	 */
	public static function sortArrayMultiFields($data, $condition)
	{
	    if (count($data) <= 0 || empty($condition)) {
	        return $data;
	    }
	    $fieldsCount = count($condition);
	    $fileds = array_keys($condition);
	    $types = array_values($condition);
	    switch ($fieldsCount) {
	        case 1:
	            $data = self::sort1Field($data, $fileds[0], $types[0]);
	            break;
	        case 2:
	            $data = self::sort2Fields($data, $fileds[0], $types[0], $fileds[1], $types[1]);
	            break;
	        default:
	            $data = self::sort3Fields($data, $fileds[0], $types[0], $fileds[1], $types[1], $fileds[2], $types[2]);
	            break;
	    }
	    return $data;
	}

	public static function sort1Field($data, $filed, $type)
	{
	    if (count($data) <= 0) {
	        return $data;
	    }
	    foreach ($data as $key => $value) {
	        $temp[$key] = $value[$filed];
	    }
	    array_multisort($temp, $type, $data);
	    return $data;
	}

	public static function sort2Fields($data, $filed1, $type1, $filed2, $type2)
	{
	    if (count($data) <= 0) {
	        return $data;
	    }
	    foreach ($data as $key => $value) {
	        $sort_filed1[$key] = $value[$filed1];
	        $sort_filed2[$key] = $value[$filed2];
	    }
	    array_multisort($sort_filed1, $type1, $sort_filed2, $type2, $data);
	    return $data;
	}

	public static function sort3Fields($data, $filed1, $type1, $filed2, $type2, $filed3, $type3)
	{
	    if (count($data) <= 0) {
	        return $data;
	    }
	    foreach ($data as $key => $value) {
	        $sort_filed1[$key] = $value[$filed1];
	        $sort_filed2[$key] = $value[$filed2];
	        $sort_filed3[$key] = $value[$filed3];
	    }
	    array_multisort($sort_filed1, $type1, $sort_filed2, $type2, $sort_filed3, $type3, $data);
	    return $data;
	}


	public static function getTimeRange($key, $days = 90)
	{
	    $now = time();
	    $days = intval($days);
	    $ret = array('stime' => 0, 'etime' => 0);

	    switch($key)
	    {
	        case 'day':// 今天
	            $ret['stime'] = strtotime(date('Y-m-d 00:00:00', strtotime('now')));
	            $ret['etime'] = strtotime(date('Y-m-d 23:59:59', strtotime('now')));
	            break;
	        case 'tomorrow':// 明天
	            $ret['stime'] = strtotime(date('Y-m-d 00:00:00', strtotime('+1 day')));
	            $ret['etime'] = strtotime(date('Y-m-d 23:59:59', strtotime('+1 day')));
	            break;
	        case 'yesterday':// 昨天
	            $ret['stime'] = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')));
	            $ret['etime'] = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));
	            break;
	        case 'after_tomorrow':// 后天
	            $ret['stime'] = strtotime(date('Y-m-d 00:00:00', strtotime('+2 day')));
	            $ret['etime'] = strtotime(date('Y-m-d 23:59:59', strtotime('+2 day')));
	            break;
	        case 'week':// 本周
	            $ret = self::getWeekRangeByTime();
	            break;
	        case 'weekend':// 本周末
	            $ret = self::getWeekEndRangeByTime();
	            break;
	        case 'last_week':// 上周
	            $time = strtotime(date('Y-m-d', strtotime('-1 week')));
	            $ret = self::getWeekRangeByTime($time);
	            break;
	        case 'last_weekend':// 上周末
	            $time = strtotime(date('Y-m-d', strtotime('-1 week')));
	            $ret = self::getWeekEndRangeByTime($time);
	            break;
	        case 'month':// 本月
	            $ret = self::getMonthRangeByTime();
	            break;
	        case 'last_month':// 上月
	            $time = strtotime(date('Y-m-1', strtotime('-1 month')));
	            $ret = self::getMonthRangeByTime($time);
	            break;
	        case 'next_month':// 下月
	            $time = strtotime(date('Y-m-1', strtotime('+1 month')));
	            $ret = self::getMonthRangeByTime($time);
	            break;
	        case 'recent':// 最近 $days 天
	            $ret = self::getDayRangeBetweenInTime($now - $days * 24 * 3600, $now);
	            break;
			case 'lately' : // 最近$days 天 不包括今天
				$ret = self::getDayRangeBetweenInTime($now - ($days+1) * 24 * 3600, $now - 24*3600);
				break;
	    }

	    return $ret;
	}


	/** 获取指定日期所在【天】的开始时间与结束时间*/
	public static function getDayRangeByTime($time = 0)
	{
	    $time = intval($time);

	    $time = ($time === 0) ? time() : $time;

	    $sdate = date('Y-m-d 00:00:00', $time);
	    $edate = date('Y-m-d 23:59:59', $time);

	    return ['stime' => strtotime($sdate), 'etime' => strtotime($edate)];
	}

	/** 获取两个时间所在【天】的开始时间与结束时间*/
	public static function getDayRangeBetweenInTime($start_time, $end_time)
	{
	    $sdate = date('Y-m-d 00:00:00', $start_time);
	    $edate = date('Y-m-d 23:59:59', $end_time);

	    return ['stime' => strtotime($sdate), 'etime' => strtotime($edate)];
	}

	/** 获取指定时间所在【星期】的开始时间与结束时间*/
	public static function getWeekRangeByTime($time = 0)
	{
	    $time = intval($time);
	    $time = ($time === 0) ? time() : $time;

	    $w = strftime('%u', $time); //当前时间对应的周几

	    $sdate = date('Y-m-d 00:00:00', $time + (1 - $w) * 86400);
	    $edate = date('Y-m-d 23:59:59', $time + (7 - $w) * 86400);

	    return ['stime' => strtotime($sdate), 'etime' => strtotime($edate)];
	}

	/** 获取指定时间所在【周末】的开始时间与结束时间*/
	public static function getWeekEndRangeByTime($time = 0)
	{
	    $time = intval($time);
	    $time = ($time === 0) ? time() : $time;

	    $w = strftime('%u', $time); //当前时间对应的周几

	    $sdate = date('Y-m-d 00:00:00', $time + (6 - $w) * 86400);
	    $edate = date('Y-m-d 23:59:59', $time + (7 - $w) * 86400);

	    return ['stime' => strtotime($sdate), 'etime' => strtotime($edate)];
	}

	/** 获取指定时间所在【月】的开始时间与结束时间*/
	public static function getMonthRangeByTime($time = 0)
	{
	    $time = intval($time);
	    $time = ($time === 0) ? time() : $time;

	    $mdays = date('t', $time); //给定时间，当月份所应有的天数

	    $stime = strtotime(date('Y-m-1 00:00:00', $time));
	    $etime = strtotime(date('Y-m-'.$mdays.' 23:59:59',$time));

	    return ['stime' => $stime, 'etime' => $etime];
	}
    

    //获取某个月的所有日期
    public static function getMonthDays($month = "this month", $format = "d") {
	    $start = strtotime("first day of $month");
	    $end = strtotime("last day of $month");
	    $days = array();
	    for($i=$start;$i<=$end;$i+=24*3600) $days[$i]['date'] = date($format, $i);
	    return array_values($days);
	}

	//获取某个时间段的每一天
	public static function every_day($stime,$etime){
		$data = [];
		$week = array('日','一','二','三','四','五','六');

		for($i=0;strtotime($stime.'+'.$i.' days') <= strtotime($etime) && $i<365;$i++){
		    $time = strtotime($stime.'+'.$i.' days');
		    $data[$i]['date'] =  date('d',$time);
		    $data[$i]['week_day'] = $week[date('w',$time)];
		}

		return $data;
	}

	/**
	 * 计算指定日期的一周开始及结束日期
	 * @param  DateTime $date  日期
	 * @param  Int      $start 周几作为一周的开始 1-6为周一~周六，0为周日，默认0
	 * @retrun Array
	 */
	public static function getWeekRange($date , $format = "Y-m-d" ,$start=0 ){

	    //当前日期  
		$sdefaultDate = date("Y-m-d");  
		//$first =1 表示每周星期一为开始日期 0表示每周日为开始日期  
		$first=1;  
		//获取当前周的第几天 周日是 0 周一到周六是 1 - 6  
		$w=date('w',strtotime($sdefaultDate));  
		//获取本周开始日期，如果$w是0，则表示周日，减去 6 天  
		$week_first		= date('Y-m-d' ,strtotime("$sdefaultDate -".($w ? $w - $first : 6).' days'));  
		//本周结束日期  
		$week_seventh	= date($format ,strtotime("$week_first +6 days"));
		//本周开始第二天
		$week_second 	= date($format ,strtotime("$week_first +1 days"));
		//本周开始第三天
		$week_third 	= date($format ,strtotime("$week_first +2 days"));
		//本周开始第四天
		$week_fourth 	= date($format ,strtotime("$week_first +3 days"));
		//本周开始第五天
		$week_fifth 	= date($format ,strtotime("$week_first +4 days"));
		//本周开始第六天
		$week_sixth 	= date($format ,strtotime("$week_first +5 days"));
	

		$data[0]['date'] 	= date($format,strtotime($week_first));
		$data[1]['date']  	= $week_second;
		$data[2]['date']  	= $week_third;
		$data[3]['date']  	= $week_fourth;
		$data[4]['date']  	= $week_fifth;
		$data[5]['date']  	= $week_sixth;
		$data[6]['date'] 	= $week_seventh;

		return $data;
	}

	//获取某一年的开始结束时间,time:时间戳
	public static function getYearRangeByTime($time){
		$time = intval($time);
	    $time = ($time === 0) ? time() : $time;

		$year=date("Y",$time);
		$first = $year."-01-01 00:00:00";
		$end   = $year."-12-31 23:59:59";

		return ['stime' => strtotime($first), 'etime' => strtotime($end)];
	}

	//获取指定时间的7天的开始结束时间
	public static function getSevenDaysRangeByTime($time){
		$time = intval($time);
	    $time = ($time === 0) ? time() : $time;

	    $sdate	= date("Y-m-d 00:00:00",$time);
		$edate	= date("Y-m-d 23:59:59",strtotime('+6 days',$time));

		return ['stime' => strtotime($sdate), 'etime' => strtotime($edate)];
	}

	//获取指定时间7天内的日期
	public static function getSevenDaysByTime($time,$format = 'Y-m-d'){
		$time = intval($time);
	    $time = ($time === 0) ? time() : $time;

	    $data[0]['date'] 	= date($format,$time);
		$data[1]['date']  	= date($format,strtotime('+1 days',$time));
		$data[2]['date']  	= date($format,strtotime('+2 days',$time));
		$data[3]['date']  	= date($format,strtotime('+3 days',$time));
		$data[4]['date']  	= date($format,strtotime('+4 days',$time));
		$data[5]['date']  	= date($format,strtotime('+5 days',$time));
		$data[6]['date'] 	= date($format,strtotime('+6 days',$time));


		return $data;
	}

}


?>