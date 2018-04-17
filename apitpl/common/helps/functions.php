<?php
namespace common\helps;

/*
 * 自定义全局公共方法
 */
class functions{

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

    
}


?>