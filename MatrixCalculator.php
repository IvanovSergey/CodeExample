<?php
class MatrixCalculator
{
	private
		$sys,
		$indicator_to_mo,
		$fact,
		$period,
		$calculation,
		$matrix_cache,
		$expression,
		$expression_hst,
		$expression_type,
			
		$expression_types,
		$expression_types_by_key	= array(),
		$expression_types_by_char	= array(),
			
		$data						= array(),	
		$cache						= array(),
		$facts_cache				= array(),
		$mo_cache					= array(),
		$mo_info					= array(),
		$childs						= array(),
			
		$task_default_mark,
		$task_mark_calculation_type,
			
		$period_start,
		$period_end
	;
	
    public function __construct($sys) {
		$this->sys = $sys;
		$this->indicator_to_mo 	= $this->sys->GetObject('IndicatorToMo');
		$this->fact 			= $this->sys->GetObject('IndicatorToMoFact');
		$this->period			= $this->sys->GetObject('IndicatorPeriod');
		$this->calculation		= $this->sys->GetObject('IndicatorCalculation');
		$this->matrix_cache		= $this->sys->GetObject('MatrixCache');
		$this->expression		= $this->sys->GetObject('IndicatorExpression');
		$this->expression_hst	= $this->sys->GetObject('IndicatorToMoExpressionHst');
		$this->expression_type	= $this->sys->GetObject('IndicatorToMoExpressionType');
		$this->expression_types	= $this->expression_type->GetFullList();
		
        foreach ($this->expression_types as $expression_type) {
        	$this->expression_types_by_key[ $expression_type['key'] ]
        		= $expression_type;
        	$this->expression_types_by_char[ $expression_type['char'] ]
        		= $expression_type;
        }
		
		// Default mark
		$option = $this->sys->GetObject('Option');
		$option_data = $option->GetItemByParams(OPTION_TASK_DEFAULT_MARK, 0);
		$this->task_default_mark = $option_data ? $option_data['value'] : 100;
		
		// Task mark calculation type
		$option_data = $option->GetItemByParams(OPTION_TASK_MARK_CALCULATION_TYPE, 0);
		$this->task_mark_calculation_type = $option_data ? $option_data['value'] : 'last';
		
		// Task Nogroup Calculation
		$option_data = $option->GetItemByParams(OPTION_TASK_NOGROUP_CALCULATION, 0);
		$this->task_nogroup_calculation = $option_data && $option_data['value'] == 1;
    }
	
    public function LoadMoCache($period_start, $period_end) {
		$period_key = $period_start . '_' . $period_end;
		if(!isset($this->mo_cache[$period_key])) {
			$this->cache			= $this->matrix_cache->GetListForMo(
				$this->sys->mo_id,
				$period_start, 
				$period_end
			);
			$this->mo_cache[$period_key] = true;
		}
	}

    public function GetExpressionTypeByKey($key) {
    	return isset($this->expression_types_by_key[$key])
    		? $this->expression_types_by_key[$key]
    		: null
		;
    }
	
    public function Calculate(&$data, $period_start, $period_end) {
		$this->data				= array();	
		$this->period_start		= $period_start;
		$this->period_end		= $period_end;
		
		// Get data with history
		if($this->sys->UseLazyCache()) {
			$add_ids = array();
			foreach ($data as $item) {
				$add_ids[] = $item['indicator_to_mo_id'];
			}
			$this->FillInIndicatorData($add_ids, true);
		} else {
			foreach ($data as $item) {
				$this->data[$item['indicator_to_mo_id']] = $this->indicator_to_mo->GetItem(
					$item['indicator_to_mo_id'], 
					$period_start, 
					$period_end, 
					true
				);
			}
		}
		
		// Get cache
		$this->LoadMoCache($period_start, $period_end);	
		$this->PreloadCache($period_start, $period_end);
		if($this->sys->UseLazyCache()) {
			$this->matrix_cache->SetUseLazyLoad(true);
		}
		// Calculate
		foreach ($data as $i => $item) {
			foreach($this->expression_types as $type) {
				$data[$i][ $type['key'] ] = $this->GetParam(
					$item['indicator_to_mo_id'],
					$type,
					$period_start,
					$period_end
				);
			}
		}
		if($this->sys->UseLazyCache()) {
			$this->matrix_cache->SetUseLazyLoad(false);
			$this->matrix_cache->SaveInternalData();
		}
    }
	
    public function CalculateParam(
    	$id,
    	$type,
    	$period_start,
    	$period_end
    ) {
		$this->data				= array();	
		$this->period_start		= $period_start;
		$this->period_end		= $period_end;
		
		return $this->GetParam(
			$id,
			$type,
			$period_start,
			$period_end,
			false
		);
	}
	
	public function PreloadCache($period_start, $period_end) {
		// Get IDs
		$preload_id_arr = array();
		foreach ($this->data as $id => $item) {
			// If not loaded by LoadMoCache
			if(!isset($this->cache[$id])) {
				$preload_id_arr[$id] = $id;
			}
		}
		if($preload_id_arr) {
			// Get cache
			$cache_data = $this->matrix_cache->GetList(
				$preload_id_arr,
				ANY,
				$period_start,
				$period_end
			);
			if ($cache_data) {
				foreach ($cache_data as $i => $item) {
					$period	= $item['period_start'] . '_' . $item['period_end'];
					$type = $this->expression_type->GetItem(
						$item['indicator_to_mo_expression_type_id']
					);
					$this->cache[$item['indicator_to_mo_id']][$type['key']][$period] = $item['value'];
				}
			}
		}
	}
		
	public function CalculateTaskParam($id, $type, $period_start, $period_end) {
		$behaviour_key = $this->data[$id]['indicator_behaviour_key'];
		$result = null;
		switch ($type['key']) {
			case 'weight':
				if($behaviour_key == 'kpi_task') {		
					$result = $this->data[$id]['weight'];
				}
				else {
					$childs = $this->GetChilds($id, $period_start, $period_end);
					if(!$childs) {
						$result = 0;
						$fact_data = $this->GetTaskFacts(
							$id,
							$period_start,
							$period_end
						);
						$exists = false;
						if ($fact_data) {
							foreach ($fact_data as $i => $item) {
								if($item['value'] == 1) {
									$exists = true;
									if($this->data[$id]['standard_operation_time']) {
										$history = $this->GetHistory($id, $period_end);
										$result += (int)$item['complexity'] * $history['standard_operation_time'];
									}
									else {
										$result += (int)$item['complexity'];
									}
								}
							}
						}
					}
					else {
						$result = 0;
						$fact = $this->GetParam(
							$id, 
							$this->expression_types_by_key['fact'],
							$period_start, 
							$period_end
						);
						if($fact == 1 || $this->task_nogroup_calculation) {
							$this->FillInIndicatorData($childs);
							foreach($childs as $child_id) {
								$result += $this->GetParam(
									$child_id, 
									$this->expression_types_by_key['weight'],
									$period_start, 
									$period_end
								);
							}
						}
					}
				}
				break;
			case 'fact':
				if($behaviour_key == 'kpi_task') {
					$result = 0;
					$childs = $this->GetChilds($id, $period_start, $period_end);
					$this->FillInIndicatorData($childs);
					foreach($childs as $child_id) {
						$weight = $this->GetParam(
							$child_id, 
							$this->expression_types_by_key['weight'],
							$period_start, 
							$period_end
						);
						$result += $weight;
					}
					$result /= 60;
				}
				else {
					if(strtotime($period_start) > strtotime($this->data[$id]['live_start'])) {
						$period_start = $this->data[$id]['live_start'];
					}
					$fact_data = $this->GetTaskFacts(
						$id,
						$period_start, 
						$period_end
					);
					$last_fact = array_pop($fact_data);
					$result = $last_fact ? intval($last_fact['value']) : null;
				}
				break;
			case 'plan':
				if($behaviour_key != 'kpi_task') {
					if($this->data[$id]['plan_default'] > 0) {
						if($this->data[$id]['standard_operation_time']) {
							$result = $this->data[$id]['plan_default'] * $this->data[$id]['standard_operation_time'];
						}
						else {
							$result = $this->data[$id]['plan_default'];
						}
					}
					else {
						// Sum
						$result = 0;
						$childs = $this->GetChilds($id, $period_start, $period_end);
						$this->FillInIndicatorData($childs);
						foreach($childs as $child_id) {
							$plan = $this->GetParam(
								$child_id, 
								$this->expression_types_by_key['plan'],
								$period_start, 
								$period_end
							);
							$result += $plan;
						}
					}
				}
				break;
			case 'res':
				if($behaviour_key != 'kpi_task') {
					$fact = $this->GetParam(
						$id, 
						$this->expression_types_by_key['fact'],
						$period_start, 
						$period_end
					);
					$result = $fact == 1 ? 100 : 0;
				}
				break;
			case 'complexity':
				$childs = $this->GetChilds($id, $period_start, $period_end);
				if($behaviour_key != 'kpi_task'
					&& !$childs
				) {
					// Calculate unknown parameter value
					$result = 0;
					$fact_data = $this->GetTaskFacts(
						$id,
						$period_start,
						$period_end
					);
					
					$exists = false;
					if ($fact_data) {
						// Sum
						foreach ($fact_data as $i => $item) {
							if($item['value'] != 1) {
								$exists = true;
								if($this->data[$id]['standard_operation_time']) {
									$history = $this->GetHistory($id, $period_end);
									$result += (int)$item['complexity'] * $history['standard_operation_time'];
								}
								else {
									$result += (int)$item['complexity'];
								}
							}
						}
					}
					if (!$exists) {
						$result = '~';
					}
				}
				else {
					// Sum
					$result = 0;
					$this->FillInIndicatorData($childs);
					foreach($childs as $child_id) {
						$complexity = $this->GetParam(
							$child_id, 
							$this->expression_types_by_key['complexity'],
							$period_start, 
							$period_end
						);
						$result += $complexity;
					}
				}
				break;
			case 'mark':
				if($behaviour_key == 'kpi_task') {
					// Average mark for all tasks
					$result	= 0;
					$count	= 0;
					$childs = $this->GetChilds($id, $period_start, $period_end);
					$this->FillInIndicatorData($childs);
					foreach($childs as $child_id) {
						$mark = $this->GetParam(
							$child_id, 
							$this->expression_types_by_key['mark'],
							$period_start, 
							$period_end
						);
						if($mark !== '~') {
							$result += $mark;
							$count++;
						}
					}
					if($count) {
						$result /= $count;
					}
				}
				else {
					$fact = $this->GetParam(
						$id, 
						$this->expression_types_by_key['fact'],
						$period_start, 
						$period_end
					);
					//if($fact == 1) { // Ok
						$fact_data = $this->GetTaskFacts(
							$id,
							$period_start,
							$period_end
						);
						switch ($this->task_mark_calculation_type) {
							case 'last':
								// Last mark
								$last_fact = array_pop($fact_data);
								if($last_fact['mark'])
									$result = intval($last_fact['mark']);
								elseif($last_fact['mark_ignored'])
									$result = '~';
								elseif($fact == 1)
									$result = $this->task_default_mark;
								else
									$result = 0;
								break;

							case 'mean':
								// Mean mark value
								$count = 0;
								$ignored = 0;
								foreach ($fact_data as $fact_item) {
									if($fact_item['mark'] !== null && !$fact_item['mark_ignored']) {
										$count++;
										$result += (int)$fact_item['mark'];
									} elseif($fact_item['mark_ignored'])
										$ignored++;
								}
								if($count)
									$result = $result / $count;
								elseif($ignored)
									$result = '~';
								elseif($fact == 1)
									$result = $this->task_default_mark;
								else
									$result = 0;
								break;

							default:
								break;
						}
					/*}
					else {
						$result = '~';
					}*/
				}
				break;
			case 'cost':	
				$mo_id = $this->GetMoId($id, $period_start, $period_end);
				$mo_info = $this->GetMoInfo($mo_id, $period_start, $period_end);
				if($behaviour_key == 'kpi_task') {
					$result	= 0;
					$childs = $this->GetChilds($id, $period_start, $period_end);
					$this->FillInIndicatorData($childs);
					foreach($childs as $child_id) {
						$cost = $this->GetParam(
							$child_id, 
							$this->expression_types_by_key['cost'],
							$period_start, 
							$period_end
						);
						if($cost !== '~') {
							$result += $cost;
						}
					}
				}
				else {
					$fact = $this->GetParam(
						$id, 
						$this->expression_types_by_key['fact'],
						$period_start, 
						$period_end
					);
					$cost_key = $fact == 1 ? 'weight' : 'plan';
					$minutes = $this->GetParam(
						$id, 
						$this->expression_types_by_key[$cost_key],
						$period_start, 
						$period_end
					);
					$result = $mo_info['tasks_hour_cost'] * $minutes / 60;
				}
				break;
			case 'cost_with_weight':	
				$mo_id = $this->GetMoId($id, $period_start, $period_end);
				$mo_info = $this->GetMoInfo($mo_id, $period_start, $period_end);
				if($behaviour_key == 'kpi_task') {
					$result	= 0;
					$childs = $this->GetChilds($id, $period_start, $period_end);
					$this->FillInIndicatorData($childs);
					foreach($childs as $child_id) {
						$cost_with_weight = $this->GetParam(
							$child_id, 
							$this->expression_types_by_key['cost_with_weight'],
							$period_start, 
							$period_end
						);
						if($cost_with_weight !== '~') {
							$result += $cost_with_weight;
						}
					}
				}
				else {
					$cost = $this->GetParam(
						$id, 
						$this->expression_types_by_key['cost'],
						$period_start, 
						$period_end
					);
					if($mo_info['tasks_weight']) {
						$result = $cost * $mo_info['tasks_weight'] / 100;
					}
				}
				break;
			default:
				break;
		}
		
		return $result;
	}
	private function FillInIndicatorData($ids, $forse_set = false) {
		if($this->sys->UseLazyCache()) {
			$add_ids = array();
			foreach($ids as $id) {
				if(!isset($this->data[$id]) || $forse_set)
					$add_ids[] = $id;
			}
			if(!empty($add_ids)) {
				$indicators_to_mo_data = $this->indicator_to_mo->GetList(
					$add_ids, 
					$this->period_start, 
					$this->period_end, 
					true
				);
				if(!empty($indicators_to_mo_data)) {
					$outside_matrix_data = $this->indicator_to_mo->GetOutsideFromList(
						$indicators_to_mo_data, 
						$this->period_start, 
						$this->period_end
					);
					if(!empty($outside_matrix_data)) {
						foreach($outside_matrix_data as $ind) {
							if(!isset($this->data[$ind['indicator_to_mo_id']]))
								$this->data[$ind['indicator_to_mo_id']] = $ind;
						}
					}
					foreach($indicators_to_mo_data as $ind) {
						$this->data[$ind['indicator_to_mo_id']] = $ind;
					}
					return true;
				}
			}
		}
		return false;
	}
	private function GetTaskFacts($indicator_to_mo_id, $period_start, $period_end) {
		$key = $indicator_to_mo_id . '_' . $period_start . '_' . $period_end;
		if(!isset($this->facts_cache[$key])) {
			$this->facts_cache[$key] = $this->sys->db->GetIndicatorToMoFactValuesList(
				$indicator_to_mo_id,
				$this->sys->db->ConvertDateTimeToDbDateTime($period_start),
				$this->sys->db->ConvertDateTimeToDbDateTime($period_end),
				FACT,
				false
			);
		}
		return $this->facts_cache[$key];
	}
		
	private function GetMoId($itm_id, $period_start, $period_end) {
		if(!$this->data[$itm_id]) {
			$this->data[$itm_id] = $this->indicator_to_mo->GetItem(
				$itm_id, 
				$period_start, 
				$period_end, 
				true
			);
		}
		return $this->data[$itm_id]['mo_id'];
	}
	
	public function GetMoInfo($mo_id, $period_start, $period_end) {		
		$key = $mo_id . '_' . $period_start . '_' . $period_end;
		if(!isset($this->mo_info[$key])) {
			// Get pay and tasks itm id
			$itm_data = $this->indicator_to_mo->GetListForMo(
				$mo_id,
				$period_start,
				$period_end,
				true
			);
			$pay_id = null;
			$tasks_id = null;
			$mo_info = array();
			if($itm_data) {
				foreach ($itm_data as $i => $item) {
					if($item['indicator_behaviour_key'] == 'kpi_pay'
						&& $item['pid'] == 0
					) {
						$pay_id = $item['indicator_to_mo_id'];
					}
					if($item['indicator_behaviour_key'] == 'kpi_task') {
						$tasks_id = $item['indicator_to_mo_id'];
					}
					if(!isset($this->data[$item['indicator_to_mo_id']]))
						$this->data[$item['indicator_to_mo_id']] = $item;
				}
			}
			
			// Get pay plan for mo
			$pay_plan = $this->GetParam(
				$pay_id, 
				$this->expression_types_by_key['plan'],
				$period_start, 
				$period_end
			);
			
			// Get tasks plan for mo
			$tasks_plan = $this->GetParam(
				$tasks_id, 
				$this->expression_types_by_key['plan'],
				$period_start, 
				$period_end
			);

			$mo_info['tasks_hour_cost'] = 0;
			if($pay_plan > 0 && $tasks_plan > 0) {
				$mo_info['tasks_hour_cost'] =  $pay_plan / $tasks_plan;
			}
			
			// Get tasks weight for mo
			$mo_info['tasks_weight'] = $this->GetParam(
				$tasks_id, 
				$this->expression_types_by_key['weight'],
				$period_start, 
				$period_end
			);

			$this->mo_info[$key] = $mo_info;
		}
		return $this->mo_info[$key];
	}
		
	public function GetIdFromString($str) {
    	return preg_replace('/[^\d]+/', '', $str);
    }
	
    private function GetChilds($id, $period_start, $period_end) {
		$mo_id = $this->data[$id]['mo_id'];
		$key = $mo_id . '_' . $period_start . '_' . $period_end;
		if(!isset($this->childs[$key])) {
			$this->childs[$key] = array();
			// Get task ids
			$ids_data = $this->sys->db->GetIndicatorToMoIdsForMo(
				$mo_id,
				$this->sys->db->ConvertDateTimeToDbDateTime($period_start),
				$this->sys->db->ConvertDateTimeToDbDateTime($period_end)
			);

			$ids = array();
			foreach ($ids_data as $i => $item) {
				$ids[] = $item['indicator_to_mo_id'];
			}

			if($ids) {
				// Get pids
				$pids_data = $this->sys->db->GetIndicatorToMoPidHst(
					$ids,		
					$this->sys->db->ConvertDateTimeToDbDateTime($period_end),
					$this->sys->db->ConvertDateTimeToDbDateTime($period_end)
				);

				// Sort childs
				$arr = array();
				foreach ($pids_data as $i => $item) {
					if(!isset($arr[$item['pid']])) {
						$arr[$item['pid']] = array();
					}
					// If task execution start is after period end - don't use it, @see IndicatorToMo::FilterListForMo()
					if(isset($this->data[$item['indicator_to_mo_id']]) 
						&& isset($this->data[$item['indicator_to_mo_id']]['indicator_behaviour_key']) 
						&& $this->data[$item['indicator_to_mo_id']]['indicator_behaviour_key'] == 'task'
						&& strtotime($this->data[$item['indicator_to_mo_id']]['execution_start']) > strtotime($period_end)
					) {
						continue;
					}
					$arr[$item['pid']][] = $item['indicator_to_mo_id'];
				}
				$this->childs[$key] = $arr;
			}
		}
		return isset($this->childs[$key][$id]) ? $this->childs[$key][$id] : array();
	}

    private function GetCacheValue(
		$id,
    	$type,
    	$period_start,
    	$period_end
	) {
    	$period	= $period_start . '_' . $period_end;
		if (isset($this->cache[$id][$type['key']][$period])) {
			return $this->cache[$id][$type['key']][$period];
		}
		elseif(!isset($this->cache[$id])) {
			// Get cache
			$cache_data = $this->matrix_cache->GetItem(
				$id,
				$type['indicator_to_mo_expression_type_id'],
				$period_start,
				$period_end
			);
			if ($cache_data) {
				$this->cache[$id][$type['key']][$period] = $cache_data['value'];
				return $cache_data['value'];
			}
		}
		return null;
	}
	
    private function GetParam(
    	$id,
    	$type,
    	$period_start,
    	$period_end,
		$final = false
    ) {
		// Check value in cache
		$result = $this->GetCacheValue($id, $type, $period_start, $period_end);
		if($result === null) {
			// Get indicator data if no exists
			if (!isset($this->data[$id])) {
				$this->data[$id] = $this->indicator_to_mo->GetItem(
					$id, 
					$this->period_start, 
					$this->period_end, 
					true
				);
			}
			
			// Calculate value if data
			if($this->data[$id]) {
				if($this->data[$id]['indicator_behaviour_key'] == 'task'
					|| ($this->data[$id]['indicator_behaviour_key'] == 'kpi_task'
						&& !in_array($type['key'], array('plan', 'res'))
					)
				) {
					// Tasks quick calc
					$result = $this->CalculateTaskParam($id, $type, $period_start, $period_end);
				}
				elseif(in_array($type['key'], array('complexity', 'mark', 'cost', 'cost_with_weight'))) {
					$result = null;
				}
				elseif($final) {					
					$option	= $this->sys->GetObject('Option');					
					$show_past = $option->getValueByParam('showPastPlansAndFacts') == '1' ? true : false;
					// One segment value					
					if($show_past === false
						&& in_array($this->data[$id]['indicator_behaviour_key'], array('pay', 'kpi_pay'))
						&& in_array($type['key'], array('plan','fact'))
						&& !$this->sys->CheckRule('admin')){						
						$pay_plan = true;						
						$result = $this->CalculateFinalParam($id, $type, $period_start, $period_end, $pay_plan);						
						}
					else{						
						$result = $this->CalculateFinalParam($id, $type, $period_start, $period_end);}
				}
				else {
					// Sub-periods values
					$result = $this->CalculateSegments($id, $type, $period_start, $period_end);
				}
			}
			else {
				// If no indicator data
				$result = '#';
			}
			
			// Save
			$period	= $period_start . '_' . $period_end;
			$this->cache[$id][$type['key']][$period] = $result;
			
			// Only resource-intensive values
			if(!$this->data[$id]
				|| ($this->data[$id]['indicator_behaviour_key'] == 'task'
					&& in_array($type['key'], array('fact', 'complexity', 'mark', 'weight'))
				)
				|| ($this->data[$id]['indicator_behaviour_key'] != 'task'
					&& !in_array($type['key'], array('complexity', 'mark', 'cost', 'cost_with_weight'))
				)
			) {
				$this->matrix_cache->SaveItem(
					$id,
					$type['indicator_to_mo_expression_type_id'],
					$result,
					$period_start,
					$period_end
				);
			}
		}
		return $result;
	}
	
	private function CalculateSegments($id, &$type, $period_start, $period_end) {
		$result		= null;
		$segments	= $this->GetSegments($id, $type, $period_start, $period_end);
		switch ($this->data[$id]['indicator_calculation_key']) {
			case 'summ':
				foreach ($segments as $segment) {				
					$sum = $this->GetParam(
						$id,
						$type,
						$segment['period_start'],
						$segment['period_end'],
						true
					);
					if ($sum !== '~' && $sum !== '#') {
						if ($result === null) {
							$result = 0;
						}
						$result += $sum;
					}
				}
				break;
			case 'mean':
				$time = 0;
				foreach ($segments as $segment) {
					$sum = $this->GetParam(
						$id,
						$type,
						$segment['period_start'],
						$segment['period_end'],
						true
					);
					if ($sum !== '~' && $sum !== '#') {
						$subperiod_time 
							= $this->sys->ParseDateTime($segment['period_end'])
							- $this->sys->ParseDateTime($segment['period_start']);
						if ($result === null) {
							$result = 0;
						}
						$result += $sum * $subperiod_time;
						$time += $subperiod_time;
					}
				}
				if (isset($result) && $time) {
					$result /= $time;
				}
				else {
					$result = '#';
				} 	
				break;
			case 'simple':
				$last_segment = $segments[count($segments) - 1];
				$result = $this->GetParam(
					$id,
					$type,
					$last_segment['period_start'],
					$last_segment['period_end'],
					true
				);
				break;
		}
		if ($result === null) {
			$result = '#';
		}
		return $result;
	}
	
	private function GetSegments($id, &$type, $period_start, $period_end) {
		$segments			= array();
		$db_period_start	= $this->sys->db->ConvertDateTimeToDbDateTime($period_start);
		$db_period_end		= $this->sys->db->ConvertDateTimeToDbDateTime($period_end);
		$hst_start			= $period_start;
		$hst_end			= null;
		$last_calculation	= null;
		foreach ($this->data[$id]['history'] as $item) {
			if($item['db_period_start'] <= $db_period_end
				&& $item['db_period_end'] >= $db_period_start
			) {
				if($last_calculation !== null
					&& $last_calculation !== $item[$type['key'] . '_calculation']
				) {
					$segments[] = array(
						'period_start'	=> $hst_start,
						'period_end'	=> $hst_end
					);
					$hst_start = $item['period_start'];
				}
				$last_calculation	= $item[$type['key'] . '_calculation'];
				$hst_end			= $item['period_end'];
			}
		}
		if($last_calculation !== null) {
			$segments[] = array(
				'period_start'	=> $hst_start,
				'period_end'	=> $period_end
			);
		}
		return $segments;
	}
	
	private function GetHistory($id, $till) {
		$db_till = $this->sys->db->ConvertDateTimeToDbDateTime($till);
		foreach ($this->data[$id]['history'] as $item) {
			if($item['db_period_end'] >= $db_till) {
				return $item;
			}
		}
	}

	private function CalculateFinalParam(
    	$id,
    	$type,
    	$period_start,
    	$period_end,
		$pay_plan = false
	) {		
		$history = $this->GetHistory($id, $period_end);
		$result = null;
		// Calculate	
		if (isset($history[$type['key'] . '_calculation']) 
			&& trim($history[$type['key'] . '_calculation']) !== ''
		) {
			// Parameter is complex and calculates by formula
			$calculation = ' ' . $history[$type['key'] . '_calculation'] . ' ';
			$dependences = trim($history[$type['key'] . '_dependences']);
			
			// Check function params
			$arr = $dependences ? explode(' ', $dependences) : array();
			
			$dep_ids = array();
			$dep_ids_need_load = array();
			if($this->sys->UseLazyCache()) {
				foreach ($arr as $dep) {
					$dep_ids[$dep] = $this->GetIdFromString($dep);
					if(!isset($this->data[$dep_ids[$dep]])) {
						$dep_ids_need_load[] = $dep_ids[$dep];
					}
				}
				if(!empty($dep_ids_need_load)) {
					$this->FillInIndicatorData($dep_ids_need_load);
				}
			}
			foreach ($arr as $dep) {
				$dep_id	= isset($dep_ids[$dep]) ? $dep_ids[$dep] : $this->GetIdFromString($dep);
				$dep_type = isset($this->expression_types_by_char[ $dep[0] ])
					? $this->expression_types_by_char[ $dep[0] ]
					: '';
				$dep_value = $this->GetParam(
					$dep_id,
					$dep_type,
					$period_start,
					$period_end
				);
				if ($dep_value !== null) {
					// Twice
					for ($i = 0; $i < 2; $i++) {
						$calculation = str_replace(" $dep ", " $dep_value ", $calculation);
					}
				}
			}
			$calculation = trim($calculation);
			try {
				$result = $this->expression->Calculate($calculation);
			}
			catch (Exception $e) {
				$result = 0;
			}
		}
		else {
			// Parameter is simple
			switch ($type['key']) {
				case 'weight':
					$result = $history['weight'];
					break;
				case 'fact':
					// Periodic counting
					$show_facts	= true;
					if($this->data[$id]['plan_periodic_counting']) {
						$included			= 0;
						$result				= 0;
						$moment				= $period_start;
						$period_end_time	= $this->period->MakeTime($period_end);
						do {
							$subperiod = $this->period->GetPeriodForKey(
								$history['plan_period_key'],
								$moment
							);
							$subperiod_end_time = $this->period->MakeTime(
								$subperiod['period_end']
							);
							if($period_end_time >= $subperiod_end_time) {
								// All subperiod facts
								if($included == 0) {
									$period_start = $subperiod['period_start'];
								}
								$period_end = $subperiod['period_end'];
								$included++;
							}
							$next_start_time = $subperiod_end_time + DAY_TIME;
							$moment	= $this->sys->GetDateTime($next_start_time);
						}
						while ($subperiod_end_time <= $period_end_time);
						$show_facts = $included > 0;
					}
					if($show_facts) {
						$fact_data = $this->fact->GetFactValuesList(
							$id,
							$period_start,
							$period_end,
							NOT_NULL,
							false,
							'value',
							$pay_plan
						);
						if ($fact_data) {
							$result = $this->calculation->Calculate(
								$this->data[$id]['indicator_calculation_key'],
								$fact_data
							);
						}
						else {
							$fact_data = $this->fact->GetFactValuesList(
								$id,
								$period_start,
								$period_end
							);
							$result = $fact_data ? '~' : '#';
						}
					}
					break;
				case 'plan':
					$period_start_time	= $this->period->MakeTime($period_start);
					$period_end_time	= $this->period->MakeTime($period_end);
					$moment				= $period_start;
					$num				= 0;
					do {
						$subperiod = $this->period->GetPeriodForKey(
							$history['plan_period_key'],
							$moment
						);
						$subperiod_start_time = $this->period->MakeTime(
							$subperiod['period_start']
						);
						$subperiod_end_time = $this->period->MakeTime(
							$subperiod['period_end']
						);
						
						if(strtotime($moment) >= strtotime($this->data[$id]['live_start'])
							&& strtotime($moment) <= strtotime($this->data[$id]['live_end'])
						) {		
							$fact_data = $this->fact->GetPlanValuesList(
								$id,
								$subperiod['period_start'],
								$subperiod['period_end'],
								NOT_NULL,
								'value',
								$pay_plan
							);
							if ($fact_data) {
								$sum = $this->calculation->Calculate(
									$this->data[$id]['indicator_calculation_key'],
									$fact_data
								);
							}
							else {
								$fact_data = $this->fact->GetPlanValuesList(
									$id,
									$subperiod['period_start'],
									$subperiod['period_end']
								);
								$sum = $fact_data?'~':'#';
							}

							$delta = (
								min($period_end_time + DAY_TIME - 1, $subperiod_end_time + DAY_TIME - 1) -
								max($period_start_time, $subperiod_start_time)
							) / (
								$subperiod_end_time + DAY_TIME - 1 - $subperiod_start_time
							);
							// Periodic counting
							if($this->data[$id]['plan_periodic_counting']) {
								$delta = $period_end_time >= $subperiod_end_time ? 1 : 0;
							}

							switch ($this->data[$id]['indicator_calculation_key']) {
								case 'summ':
									if ($sum !== '~' && $sum !== '#') {
										if ($result !== null) {
											$result += $sum * $delta;
										}
										else {
											$result = $sum * $delta;
										}
									}
									break;
								case 'mean':
									if ($sum !== '~' && $sum !== '#') {
										$num += $delta;
										if ($result !== null) {
											$result += $sum * $delta;
										}
										else {
											$result = $sum * $delta;
										}
									}
									break;
								case 'simple':
									break;
							}
						}
							
						$next_start_time = $subperiod_end_time + DAY_TIME;
						$moment	= $this->sys->GetDateTime($next_start_time);
					}
					while ($subperiod_end_time < $period_end_time);
					switch ($this->data[$id]['indicator_calculation_key']) {
						case 'mean':
							if ($num) {
								$result = $result / $num;
							}
							break;
						case 'simple':
							if ($this->data[$id]['indicator_behaviour_key'] === 'task' &&
								$type['key'] === 'fact'
							) {
								$subperiod['period_start'] = $this->data[$id]['live_start'];
							}

							$fact_data = $this->fact->GetPlanValuesList(
								$id,
								$subperiod['period_start'],
								$subperiod['period_end'],
								NOT_NULL
							);
							if ($fact_data) {
								$result = $this->calculation->Calculate(
									$this->data[$id]['indicator_calculation_key'],
									$fact_data
								);
							}
							else {
								$fact_data = $this->fact->GetPlanValuesList(
									$id,
									$subperiod['period_start'],
									$subperiod['period_end']
								);
								if ($fact_data) {
									$result = '~';		
								}
							}
							break;
					}
					if ($result === null) {
						$result = '#';
					}
					break;
				case 'res':
					$result = '#';
					break;
			}
		}
		return $result;
    }
}
?>
