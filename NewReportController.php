<?php

class NewReportController extends Controller
{
	/**
	* Report list
	*/
	public function ActionList() {
		$this->sys->check->Start();
		$results = array();
		$report = $this->sys->GetObject('NewReport');
		$results['report_list'] = $report->GetList();
		$this->sys->check->Finish($results);
	}
	
	/**
	* Load report params
	*/
	public function ActionLoad() {
		$this->sys->check->Start();
		$report = $this->sys->GetObject('NewReport');
		$report_id = $this->sys->GetIndex('report_id');
		$report_data = $report->GetItem($report_id);
		$results = array('report' => $report_data);
		$this->sys->check->Finish($results);
	}
		
	public function ActionGetParams() {
		$this->sys->check->Start();
    	$results = array(
    		'mo_list'			=> array(),
    		'indicator_list'	=> array(),
    		'field_list'		=> array(),
    		'option_list'		=> array()
    	);
    	
    	$mo = new Mo($this->sys);
    	$user_to_mo = new UserToMo($this->sys);
		$hidden = $this->sys->CheckRule('user_mo_hidden') ? ANY : 0;
    	$mo_data = $mo->GetList(
    		MO_TYPE_WORKER,
    		$this->sys->period_start,
    		$this->sys->period_end,
			$hidden
    	);
    	$mo_cnt = count($mo_data);
		$user_names = $user_to_mo->GetUsersListForMoFromMoList($mo_data);
    	for ($i = 0; $i < $mo_cnt; $i++) {
			// @deprecated ugly usage - too many mysql queries
			/*$user_name = $user_to_mo->GetUserListForMo(
				$mo_data[$i]['mo_id'],
				$this->sys->period_start,
				$this->sys->period_end
				);*/
    		$results['mo_list'][] = array(
    			'mo_id'		=> $mo_data[$i]['mo_id'],
    			'pid'		=> $mo_data[$i]['pid'],
    			'name'		=> $mo_data[$i]['name'],
    			'user_name'	=> isset($user_names[$mo_data[$i]['mo_id']])
					? $user_names[$mo_data[$i]['mo_id']]
					: $mo_data[$i]['mo_position_name']
    		);
    	}
    	
    	$indicator = new Indicator($this->sys);
    	$indicator_data = $indicator->GetList(LIVING);
    	$indicator_cnt = count($indicator_data);
    	for ($i = 0; $i < $indicator_cnt; $i++) {
    		if (!$indicator_data[$i]['indicator_behaviour_hidden']) {
    			$results['indicator_list'][] = array(
    				'indicator_id'	=> $indicator_data[$i]['indicator_id'],
    				'pid'			=> $indicator_data[$i]['pid'],
    				'name'			=> $indicator_data[$i]['name'],
    				'class'			=> $indicator_data[$i]['indicator_behaviour_key'] . '_item'
    			);
			}
    	}
    	
    	$report_field = new NewReportField($this->sys);
    	$report_field_data = $report_field->GetList();
    	$report_field_cnt = count($report_field_data);
    	for ($i = 0; $i < $report_field_cnt; $i++) {
    		$results['field_list'][] = array(
    			'report_field_id'	=> $report_field_data[$i]['report_field_id'],
    			'key'				=> $report_field_data[$i]['key']
    		);
    	}
   	
    	$report_option = new NewReportOption($this->sys);
    	$report_option_data = $report_option->GetList();
		function cmp($a, $b) {
			if ($a['order']<$b['order']) return -1;
			else if ($a['order']>$b['order']) return 1;
			else return 0;
		}
		usort($report_option_data, "cmp");
    	foreach ($report_option_data as $i=>$option) {
    		$results['option_list'][] = array(
    			'report_option_id'	=> $option['report_option_id'],
    			'key'				=> $option['key']
    		);
    	}
    	$this->sys->check->Finish($results);
	}
	
	
	/**
	* Report data
	*/
	public function ActionGetData() {
		$this->sys->check->Start();
		// We will not use cahce data here as it require more CPU as we have now.
		// It will make less database requests, but as we have low CPU - all request will be slow.
		$useCacheData = false;
		if($useCacheData) {
			$this->sys->db->EnableCache('GetUser');
			$this->sys->db->EnableCache('GetIndicatorToMoHst');
			$this->sys->db->EnableCache('GetMatrixCache');
			$this->sys->db->EnableCache('GetMo');
			$this->sys->db->EnableCache('GetIndicatorToMoFactList');
			$this->sys->db->EnableCache('GetIndicatorToMoFactValuesList');
			
			$this->sys->GetObject('MatrixCache')->SetUseLazyLoad(true);
		}
        $mo                     = $this->sys->GetObject('Mo');
        $user_to_mo             = $this->sys->GetObject('UserToMo');
        $indicator              = $this->sys->GetObject('Indicator');
        $indicator_to_mo        = $this->sys->GetObject('IndicatorToMo');
        $indicator_to_mo_fact   = $this->sys->GetObject('IndicatorToMoFact');
        $indicator_to_mo_expression_type
                                = $this->sys->GetObject('IndicatorToMoExpressionType');
        $item_field_value       = $this->sys->GetObject('ItemFieldValue');
        $matrix_calculator      = $this->sys->GetObject('MatrixCalculator');
        $report_field           = $this->sys->GetObject('NewReportField');
        $period_generator       = $this->sys->GetObject('IndicatorPeriod');

        $results = array(
			'mo_list'			=> array(),
			'indicator_list'	=> array(),
			'field_list'		=> array(),
			'option_list'		=> array()
		);
		$mo_list        = $this->sys->GetParam('mo_list');
        $mo_all         = $this->sys->GetIndex('mo_all');
		$indicator_list = $this->sys->GetParam('indicator_list');
		$indicator_all  = $this->sys->GetIndex('indicator_all');
        $field_list     = $this->sys->GetParam('field_list');
		$option_list    = $this->sys->GetParam('option_list');
        $period_start   = $this->sys->GetParam('period_start');
        $period_end     = $this->sys->GetParam('period_end');
		$excel			= $this->sys->GetIndex('excel');
		
        if (!$period_start) {
            $period_start = $this->sys->period_start;
        }
        if (!$period_end) {
            $period_end = $this->sys->period_end;
        }

        $type_data = $indicator_to_mo_expression_type->GetFullList();
		$type_cnt = count($type_data);
		
        $total_option            = false;
        $mean_option             = false;
        $with_photo_option       = false;
        $only_used_option        = false;
        $only_with_weight_option = false;
        $use_period_option       = false;
        $period_key_option       = false;
        
        if ($option_list) {
        	parse_str($option_list, $option_list);
            foreach ($option_list as $option_id=>$option_value) {
                $results['option_list'][$option_id] = $option_id;
                if ($option_id == 9) { // Replace with key check
                    $total_option = true;
                }
                if ($option_id == 10) { // Replace with key check
                    $mean_option = true;
                }
                if ($option_id == 11) { // Replace with key check
                    $with_photo_option = true;
                }
                if ($option_id == 12) { // Replace with key check
                    $only_used_option = true;
                }
                if ($option_id == 14) { // Replace with key check
                    $only_with_weight_option = true;
                }
                if ($option_id == 15) { // Replace with key check
                    $use_period_option = true;                    
                }
                if ($option_id == 16) { // Replace with key check
                    $period_key_option = $option_value;                    
                }
            }
        }
        /**
        * @var IndicatorPeriod
        */
        $period_generator;
        $period_end_time = $period_generator->MakeTime($period_end);
        $periods = array();
        if ($use_period_option) {
			$period_current = $period_generator->GetPeriodForKey($period_key_option, $period_start);
			$period_current['period_start'] = $period_start;
			$period_current_end_time = $period_generator->MakeTime($period_current['period_end']);
			while ($period_end_time > $period_current_end_time) {
				$periods[] = $period_current;
				$period_current = $period_generator->GetPeriodForKey($period_key_option, $this->sys->GetDateTime($period_current_end_time+DAY_TIME));
				$period_current_end_time = $period_generator->MakeTime($period_current['period_end']);
			}			
			$periods[] = array(
		        'period_start'	=> $period_current['period_start'],
		        'period_end'	=> $period_end
		    );
        }
        else {
			$periods[] = array(
	            'period_start'	=> $period_start,
	            'period_end'	=> $period_end
	        );
        }
        $mo_data = array();
        if ($mo_all) {
            $mo_data = $mo->GetList(MO_TYPE_WORKER);
        }
        else {
            foreach ($mo_list as $mo_id) {
                $mo_data[] = $mo->GetItem($mo_id);
            }
        }
        if ($with_photo_option) {
            $user = $this->sys->GetObject('User');
        }
        foreach ($mo_data as $item) {
	        foreach($periods as $ind=>$current_period) {
	            $photo = '';
	            if ($with_photo_option) {
	                $user_to_mo_data = $user_to_mo->GetListForMo(
	                    $item['mo_id'],
	                    $current_period['period_start'],
	                    $current_period['period_end']
	                );
	                if ($user_to_mo_data) {
	                    $user_data = $user->GetItem($user_to_mo_data[0]['user_id']);
	                    if ($user_data) {
	                        $photo = $user_data['photo'];
	                    }
	                }
	            }
	            $results['mo_list'][] = array(
	                'mo_id'     => $item['mo_id'].'_'.$ind,
	                'pid'       => $item['pid'],
	                'name'      => $item['name'],
	                'period'	=> $current_period,
	                'period_f'	=> $current_period['period_start'].' - '.$current_period['period_end'],
	                'user_name' => $user_to_mo->GetUserListForMo(
	                    $item['mo_id'],
	                    $current_period['period_start'],
	                    $current_period['period_end']
	                ),
	                'photo'     => $photo
	            );
	        }
		}
		
        $indicator_data = array();
        if ($indicator_all) {
            $data = $indicator->GetList(LIVING);
            if ($data) {
                foreach ($data as $item) {
                    if (!$item['indicator_behaviour_hidden']) {
                        $indicator_data[] = $item;
                    }
                }
            }
        }
        else {
            
            foreach ($indicator_list as $indicator_id) {
			    $indicator_data[] = $indicator->GetItem($indicator_id);
            }
		}
        
        if ($only_used_option) {
            $used_indicator_arr = array();
            foreach ($mo_data as $item) {
	            foreach($periods as $ind=>$current_period) {
	                $used_data = $indicator_to_mo->GetListForMo(
	                    $item['mo_id'],
	                    $current_period['period_start'],
	                    $current_period['period_end']
	                );
	                if ($used_data) {
	                    foreach ($used_data as $used_item) {
	                        if ($only_with_weight_option && !$used_item['weight']) {
	                            continue;
	                        }
	                        $used_indicator_arr[] = $used_item['indicator_id'];
	                    }
	                }
				}
            } 
            
        }
        
        foreach ($indicator_data as $item) {
            if ($only_used_option && !in_array($item['indicator_id'], $used_indicator_arr)) {
                continue;
            }
            $results['indicator_list'][ $item['indicator_id'] ] = array(
                'indicator_behaviour_key'   => $item['indicator_behaviour_key'],
                'name'                      => $item['name'],
				'plan_common'               => $item['plan_common'],
				'fact_common'               => $item['fact_common'],
            );
        }
		
        if ($field_list) {
            foreach ($field_list as $field_id) {
			    $field_data = $report_field->GetItem($field_id);
			    $results['field_list'][$field_id] = array('key' => $field_data['key']);
		    }
        }
        
        
		
        if ($total_option) {
            $total_arr = array(
                'mo_id' => -1,
                'pid'   => 0
            );
        }
        if ($mean_option) {
            $mean_arr = array(
                'mo_id' => -2,
                'pid'   => 0
            );
        }

		$users = array();
		foreach ($results['mo_list'] as $mo_id => $item) {
			$arr = $item;
			$current_period = $item['period'];
	        foreach ($results['indicator_list'] as $indicator_id => $item) {
				$indicator_to_mo_data = $indicator_to_mo->GetItemByParams(
   					$arr['mo_id'],
   					$indicator_id,
   					$current_period['period_start'],
                    $current_period['period_end']
   				);
   				if ($indicator_to_mo_data) {
					if ($only_with_weight_option && !$indicator_to_mo_data['weight']) {
	                    continue;
	                }
	            
	                $arr['indicator_behaviour_key' . $indicator_id]	= $results['indicator_list'][$indicator_id]['indicator_behaviour_key'];
					$arr['indicator_to_mo_id' . $indicator_id] = $indicator_to_mo_data['indicator_to_mo_id'];
   					$arr['pid' . $indicator_id] = $indicator_to_mo_data['pid'];
   					
	                if ($total_option) {
	                    $total_arr['indicator_to_mo_id' . $indicator_id] = $indicator_to_mo_data['indicator_to_mo_id'];
	                }
	                if ($mean_option) {
	                    $mean_arr['indicator_to_mo_id' . $indicator_id] = $indicator_to_mo_data['indicator_to_mo_id'];
	                }
	                
	                foreach ($field_list as $field_id) {
   						switch ($field_id) {   							
   							case 16: //period
   								$arr['period' . $indicator_id] = $current_period['period_start'].' - '.$current_period['period_end'];
   								break;
   							case 1: // weight
   							case 2: // fact
   							case 3: // plan
   							case 4: // res
   								$fid = $field_id-1;
   								$arr[ $type_data[$fid]['key'] . $indicator_id ]
	                                   = $matrix_calculator->CalculateParam(
	                                       $indicator_to_mo_data['indicator_to_mo_id'],
	                                       $type_data[$fid],
	                                       $current_period['period_start'],
                    					   $current_period['period_end']
	                                   );
	                            if ($total_option) {
	                                if (!isset($total_arr[ $type_data[$fid]['key'] . $indicator_id ])) {
	                                    $total_arr[ $type_data[$fid]['key'] . $indicator_id ] = 0;
	                                }
	                                $total_arr[ $type_data[$fid]['key'] . $indicator_id ]
	                                    += $arr[ $type_data[$fid]['key'] . $indicator_id ];
	                            }
	                            if ($mean_option) {
	                                if (!isset($mean_arr[ $type_data[$fid]['key'] . $indicator_id ])) {
	                                    $mean_arr[ $type_data[$fid]['key'] . $indicator_id ] = 0;
	                                }
	                                $mean_arr[ $type_data[$fid]['key'] . $indicator_id ]
	                                    += $arr[ $type_data[$fid]['key'] . $indicator_id ];
	                            }
	                            $arr[ $type_data[$fid]['key'] . '_expression' . $indicator_id ]
   									= $indicator_to_mo_data[ $type_data[$fid]['key'] . '_expression' ];
   								break;
   							case 12: // res_icon
	                            $val = $matrix_calculator->CalculateParam(
	                                       $indicator_to_mo_data['indicator_to_mo_id'],
	                                       $type_data[3],
	                                       $current_period['period_start'],
                    					   $current_period['period_end']
	                                   );
	                            $arr[ 'res_icon' . $indicator_id ] = 'traffic_light_red';
	                            if (!in_array($val, array('-', '#', '~', NULL, 'NULL'))) {
	                                $val = intval($val);
	                                if ($val >= 100) {
	                                    $arr[ 'res_icon' . $indicator_id ] = 'traffic_light_green';
	                                }
	                                else if ($val >= 50) {
	                                    $arr[ 'res_icon' . $indicator_id ] = 'traffic_light_yellow';
	                                }   
	                            }
	                            break;
	                        case 5: // facts
   							case 6: // plans
								$field = ($field_id == 5?'fact_data':'plan_data') . $indicator_id;
								$arr[$field] = $indicator_to_mo_fact->GetList(
   									$indicator_to_mo_data['indicator_to_mo_id'],
   									$current_period['period_start'],
                    				$current_period['period_end'],
   									$field_id == 5?FACT:PLAN
   								);
   								$fact_cnt = count($arr[$field]);
   								for ($i = 0; $i < $fact_cnt; $i++) {
   									unset($arr[$field][$i]['user']);
   									if (isset($arr[$field][$i]['indicator_to_mo_fact_id'])) {
   										$arr[$field][$i]['fields']
   											= $item_field_value->GetListForItem(
   												'indicator_to_mo_fact',
   												$arr[$field][$i]['indicator_to_mo_fact_id']
   											);
   										$field_cnt = count($arr[$field][$i]['fields']);
   										for ($j = 0; $j < $field_cnt; $j++) {
   											$arr[$field][$i]['fields'][$j]
   												= $arr[$field][$i]['fields'][$j]['value'];
   										}
   									}
   								}
   								break;
   							case 7: // struct
   								$field = 'struct_data' . $indicator_id;
   								$out = array();
   								
   								$user = $this->sys->GetObject('User');
								if($indicator_to_mo_data['indicator_behaviour_key'] == 'kpi_task') {
									$arr[$field] = $indicator_to_mo->GetListForAncestor(
										$indicator_to_mo_data['indicator_to_mo_id'],
										$current_period['period_start'],
                    					$current_period['period_end']
									);
								}
								else {
									$arr[$field] = $indicator_to_mo->GetListForParent(
										$indicator_to_mo_data['indicator_to_mo_id'],
										$current_period['period_start'],
                    					$current_period['period_end']
									);
								}

   								$child_cnt = count($arr[$field]);
								
								$matrix_calculator->Calculate(
									$arr[$field],
									$current_period['period_start'],
                    				$current_period['period_end']
								);
   								
   								for ($i = 0; $i < $child_cnt; $i++) {
   									if ($arr[$field][$i]['indicator_behaviour_key'] == 'kpi_task' ||
                            			$arr[$field][$i]['indicator_behaviour_key'] == 'task') {
                            		
                            			$val = intval($arr[$field][$i]['fact']);
                                    	if (!isset($out[$val])) $out[$val] = 0;
                                    	$out[$val] += $arr[$field][$i]['weight'];
                        			}					
									
									// Author
									$author_id = $arr[$field][$i]['author_id'];
									if(!isset($users[$author_id])) {
										$users[$author_id] = $user->GetItem($author_id);
									}
									$arr[$field][$i]['author'] = $users[$author_id]['short_name'];
   								}
   								break;
   							case 8: // Interpretation
   								$arr['res_img' . $indicator_id]
                                	= $indicator_to_mo_data['indicator_to_mo_id'];
	                            $indicator_interpretation_point
	                                = new IndicatorInterpretationPoint($this->sys);
	                            $indicator_interpretation_point_data
	                                = $indicator_interpretation_point->GetList(
	                                    $indicator_to_mo_data['indicator_interpretation_id']
	                                );
	                            $arr['res_img_points' . $indicator_id]
	                                = $indicator_interpretation_point_data;
   								break;
	                        case 9:  // recursive facts
	                        case 10: // recursive plans
	                            $field = ($field_id == 9?'facts_rec_data':'plans_rec_data') . $indicator_id;
	                            $child_data = $indicator_to_mo->GetListForParent(
	                                $indicator_to_mo_data['indicator_to_mo_id'],
	                                $current_period['period_start'],
                    				$current_period['period_end']
	                            );
	                            $arr[$field] = array();
	                            foreach($child_data as $child) {
	                                $child = array(
	                                    'indicator_to_mo_id'     => $child['indicator_to_mo_id'],
	                                    'name'                   => $child['name']
	                                );
	                                $child['items'] = $indicator_to_mo_fact->GetList(
	                                    $child['indicator_to_mo_id'],
	                                    $this->sys->period_start,
	                                    $this->sys->period_end,
	                                    $field_id == 9?FACT:PLAN
	                                );
	                                $fact_cnt = count($child['items']);
	                                for ($i = 0; $i < $fact_cnt; $i++) {
	                                    //unset($child['items'][$i]['user']);
	                                    if (isset($child['items'][$i]['indicator_to_mo_fact_id'])) {
	                                        $child['items'][$i]['fields']
	                                            = $item_field_value->GetListForItem(
	                                                'indicator_to_mo_fact',
	                                                $child['items'][$i]['indicator_to_mo_fact_id']
	                                            );
	                                        $field_cnt = count($child['items'][$i]['fields']);
	                                        for ($j = 0; $j < $field_cnt; $j++) {
	                                            $child['items'][$i]['fields'][$j]
	                                                = $child['items'][$i]['fields'][$j]['value'];
	                                        }
	                                    }
	                                }
	                                $arr[$field][] = $child;
	                            }
	                            break;
	                        case 11: // missed
	                            $arr[ 'missed_fact_data' . $indicator_id ]
	                                = count($indicator_to_mo_fact->GetMissed(
	                                    $indicator_to_mo_data['indicator_to_mo_id'],
	                                    ANY,
	                                    FACT,
	                                    $current_period['period_start'],
                    					$current_period['period_end']
	                                ));
	                            $arr[ 'missed_plan_data' . $indicator_id ]
	                                = count($indicator_to_mo_fact->GetMissed(
	                                    $indicator_to_mo_data['indicator_to_mo_id'],
	                                    ANY,
	                                    PLAN,
	                                    $current_period['period_start'],
                    					$current_period['period_end']
	                                ));
								break;
	                        case 13: // plan_responsible
	                        case 14: // fact_responsible
								$plan = $field_id == 13 ? PLAN : FACT;
								if ($item[($plan == PLAN?'plan':'fact') . '_common']) {
									$indicator_responsible = $this->sys->GetObject('IndicatorResponsible');
									$responsible_data = $indicator_responsible->GetList($indicator_to_mo_data['indicator_id']);
								}
								else {	
									$indicator_responsible = $this->sys->GetObject('IndicatorToMoResponsible');
									$responsible_data = $indicator_responsible->GetList($indicator_to_mo_data['indicator_to_mo_id']);
								}	
								if($responsible_data) {
									$arr[ ($plan == PLAN?'plan':'fact') . '_responsible_mo_id' . $indicator_id ] 
										= $responsible_data[0]['mo_id'];
									$arr[ ($plan == PLAN?'plan':'fact') . '_responsible_mo_name' . $indicator_id ]
										= $user_to_mo->GetUserListForMo($responsible_data[0]['mo_id']);
								}
								break;
   							case 15: // Graphpic
   								$arr['fact_img' . $indicator_id]
                                	= $indicator_to_mo_data['indicator_to_mo_id'];
   								break;
						}
					}
				}
			}
	        $results['mo_list'][$mo_id] = $arr;
		}
		if($useCacheData) {
			$this->sys->GetObject('MatrixCache')->SetUseLazyLoad(false);
			$this->sys->GetObject('MatrixCache')->SaveInternalData();
		}
        if ($total_option) {
            $results['total'] = $total_arr;
        }
        
		if ($use_period_option) {
			$results['use_period'] = 1;			
		}
		else {
			$results['use_period'] = 0;
		}
        
        if ($mean_option) {
            foreach ($mean_arr as $key => $val) {
                if (!in_array($key, array('mo_id', 'pid', 'name', 'user_name'))
                &&  !preg_match('/indicator_to_mo_id\-?\d+/', $key)
                ) {
                    $mean_arr[$key] = $val / count($results['mo_list']);
                }
            }
            $results['mean'] = $mean_arr;
        }
		if($excel)
		{
			try
			{
				$filename = $this->sys->auth->user_id . '_report.xls';
				
				// Create
				$excel_report = $this->sys->GetObject('ExcelReport');
				$excel_report->Save($results, $filename);
			}
			catch (Exception $e) 
			{
				echo '/* ERROR: ' . $e->GetMessage() . ' */';
				$this->sys->check->AddError('reportError');
			}
			$this->sys->check->Finish();
		}
		else
			$this->sys->check->Finish($results);

	}
		
	public function ActionGetReport() {
		$filename = $this->sys->auth->user_id . '_report.xls';

		// Redirect output to a clientвЂ™s web browser (Excel5)
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="01simple.xls"');
		header('Cache-Control: max-age=0');

		// Output
		echo file_get_contents($filename);
		exit();
		
	}
	  
    /**
     * Save
     */
	public function ActionSave() {
    	$this->sys->check->Start();
   		$results		= array();
   		$report_id		= $this->sys->GetIndex('report_id');
   		$name			= $this->sys->GetParam('name');
        $sort_key       = $this->sys->GetParam('sort_key');
        $sort_mode      = $this->sys->GetIndex('sort_mode');
        $mo_all         = $this->sys->GetIndex('mo_all');
   		$mo_list        = $this->sys->GetParam('mo_list');
        $indicator_all  = $this->sys->GetIndex('indicator_all');
        $indicator_list = $this->sys->GetParam('indicator_list');
   		$field_list		= $this->sys->GetParam('field_list');
   		$option_list	= $this->sys->GetParam('option_list');
   		
   		if ($option_list) {
			parse_str($option_list, $option_list);
		}
   	
        if ($mo_all) {
            $mo_list = array(0);
        }
        if ($indicator_all) {
            $indicator_list = array(0);
        }
        
        $this->sys->check->Run('name', $name, array('is_not_empty'));
   		
        $report = $this->sys->GetObject('NewReport');
   		
        if ($this->sys->check->IsEmpty()) {
   			$this->sys->db->StartTransaction();
			
            $report->SaveItem($report_id, $name, $sort_key, $sort_mode);
			
            if (!$report_id) {
				$report_id = $this->sys->db->GetLastId();
			}
			
            $indicator_to_report     = $this->sys->GetObject('NewIndicatorToReport');
			$mo_to_report            = $this->sys->GetObject('NewMoToReport');
            $report_field_to_report  = $this->sys->GetObject('NewReportFieldToReport');
            $report_option_to_report = $this->sys->GetObject('NewReportOptionToReport');
            
			// Safe savings
			if ($indicator_list) {
				$indicator_to_report->SaveList($report_id, $indicator_list);
			}
			if ($mo_list) {
				$mo_to_report->SaveList($report_id, $mo_list);
			}
			if ($field_list) {
				$report_field_to_report->SaveList($report_id, $field_list);
			}
			if ($option_list) {
				$report_option_to_report->SaveList($report_id, $option_list);
			}
			
            $this->sys->db->CommitTransaction();
		}
   		$results['report_id'] = $report_id;
   		$this->sys->check->Finish($results);
	}
    	
    /**
     * Delete
     */
	public function ActionDel() {
    	$report_id = $this->sys->GetIndex('report_id');
    	$this->sys->check->Start();
    	if ($report_id) {
    		$report = $this->sys->GetObject('NewReport');
    		$this->sys->db->StartTransaction();
    		$report->DeleteItem($report_id);
    		$this->sys->db->CommitTransaction();
    	}
    	$this->sys->check->Finish();
	}
}
    
?>
