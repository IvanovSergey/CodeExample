matrix.calculator = {};

matrix.calculator.cache = {};
matrix.calculator.data = {};
matrix.calculator.expression_types_by_char = {};
matrix.calculator.expression_types_by_key = {};

matrix.calculator.outside_data = {};
/**
 * Calculate provided matirx data
 * @param array data data with indicator objects for current matrix
 * @return null nothig is returned - we will calculate and save all info in elements of provided array data
 */
matrix.calculator.calculate = function(data) {
	// Cache is only for one calculation circle
	matrix.calculator.cache = {};
	
	for(var i in matrix.expression_types) {
		var expression_type = matrix.expression_types[ i ];
		matrix.calculator.expression_types_by_key[ expression_type['key'] ]
			= expression_type;
		matrix.calculator.expression_types_by_char[ expression_type['char'] ]
			= expression_type;
	}
		
	matrix.calculator.data = [];
	var period_start = period.start()
	,	period_end = period.end();

	for (var i in data) {
		matrix.calculator.data[ data[ i ]['indicator_to_mo_id'] ] = data[ i ];
	}
	var outside_data = matrix.get_outside_data();
	if(outside_data) {
		for(var i in outside_data) {
			matrix.calculator.data[ outside_data[ i ]['indicator_to_mo_id'] ] = outside_data[ i ];
			matrix.calculator.outside_data[ outside_data[ i ]['indicator_to_mo_id'] ] = outside_data[ i ];
		}
	}
	// For main data on server we substitude live_start with execution_start, and move live_start to live_start_real. Same for live_end.
	// But for some additional data we may not have this data. Let's be reinsured and make some check here.
	for(var i in matrix.calculator.data) {
		if(typeof(matrix.calculator.data[ i ].live_start_real) == 'undefined') 
			matrix.calculator.data[ i ].live_start_real = matrix.calculator.data[ i ].live_start;
		if(typeof(matrix.calculator.data[ i ].live_end_real) == 'undefined') 
			matrix.calculator.data[ i ].live_end_real = matrix.calculator.data[ i ].live_end;
	}
	// Calculate
	for ( var i in data) {
		for( var j in matrix.expression_types) {
			var type = matrix.expression_types[ j ];
			if(data[ i ]['indicator_behaviour_key'] == 'task'
				|| (data[ i ]['indicator_behaviour_key'] == 'kpi_task'
					//&& !in_array(type['key'], ['plan', 'res'])
				)
			) {
				continue;
			}
			data[ i ][ type['key'] ] = matrix.calculator.get_param(
				data[ i ]['indicator_to_mo_id'],
				type,
				period_start,
				period_end
			);
		}
	}
}

/**
 * Get cached value from current calculation if exist
 * @param numeric id indicator to mo ID
 * @param object type type of calculation - fact, plan, etc.
 * @param string period_start period start for cached value
 * @param string period_end period end for cached value
 * @return mixed cached value or null if it not exist
 */
matrix.calculator.get_cache_value = function(
		id,
    	type,
    	period_start,
    	period_end
) {
	var period = period_start+ '_'+ period_end;
	if (typeof(matrix.calculator.cache[ id ]) != 'undefined' 
		&& typeof(matrix.calculator.cache[ id ][ type['key'] ]) != 'undefined'
		&& typeof(matrix.calculator.cache[ id ][ type['key'] ][ period ]) != 'undefined'
	) {
		return matrix.calculator.cache[ id ][ type['key'] ][ period ];
	}
	if(typeof(matrix.calculator.outside_data[ id ]) != 'undefined'
		&& typeof(matrix.calculator.outside_data[ id ][ type['key'] ]) != 'undefined'
	) {
		return matrix.calculator.outside_data[ id ][ type['key'] ];
	}
	return null;
}

/**
 * Set cached value from current calculation
 * @param numeric id indicator to mo ID
 * @param object type type of calculation - fact, plan, etc.
 * @param string period_start period start for cached value
 * @param string period_end period end for cached value
 */
matrix.calculator.set_cache_value = function(
		id,
    	type,
    	period_start,
    	period_end,
		value
) {
	var period	= period_start+ '_'+ period_end;
	if(typeof(matrix.calculator.cache[ id ]) == 'undefined')
		matrix.calculator.cache[ id ] = {};
	if(typeof(matrix.calculator.cache[ id ][ type['key'] ]) == 'undefined')
		matrix.calculator.cache[ id ][ type['key'] ] = {};
	matrix.calculator.cache[ id ][ type['key'] ][ period ] = value;
}

/**
 * Retrive child items for provided indicator to mo ID
 * @param numeric id parent indicator to mo ID
 * @param string period_start period start for child
 * @param string period_end period end for child
 * @return array array with children elements, can be empty if no child were found
 */
matrix.calculator.get_childs = function(id, period_start, period_end) {
	var res = []
	,	parent_mo_id = matrix.calculator.get_mo_id(id);

	for(var i in matrix.calculator.data) {
		if((matrix.calculator.data[ i ].pid == id && (!parent_mo_id || parent_mo_id == matrix.calculator.data[ i ].mo_id))
			&& (Date.fromString(matrix.calculator.data[ i ].live_start_real).less(period_end))
			&& (Date.fromString(matrix.calculator.data[ i ].live_end_real).more(period_start))
			// And life end is after life start
			&& (Date.fromString(matrix.calculator.data[ i ].live_end_real).more(Date.fromString(matrix.calculator.data[ i ].live_start_real)))
		) {
			res.push(i);
		}
	}
	return res;
}

/**
 * Retrive facts for task
 * @param numeric id indicator to mo ID
 * @param string period_start period start for fact
 * @param string period_end period end for fact
 * @return array array with fact elements, can be empty if no fact were found
 */
matrix.calculator.get_task_facts = function(
	id,
	period_start,
	period_end
) {
	var res = [];
	if(matrix.calculator.data[id].facts_list) {
		for(var i in matrix.calculator.data[id].facts_list) {
			if(Date.fromString(matrix.calculator.data[id].facts_list[i].fact_time).inRange(period_start, period_end))
				res.push(matrix.calculator.data[id].facts_list[i]);
		}
	}
	return res;
}

/**
 * Retrive mo ID by indicator to mo ID from internal calculator data
 * @param numeric id indicator to mo ID
 * @param string period_start period start indicator, unused - added for similarity with server part
 * @param string period_end period end for indicator, unused - added for similarity with server part
 * @return mixed mo ID if such indicator to mo exist, elase - null
 */
matrix.calculator.get_mo_id = function(id, period_start, period_end) {
	if(matrix.calculator.data[id])
		return matrix.calculator.data[id]['mo_id'];
	return null;
}

/**
 * Retrive current mo info, all parameters are unused - added for similarity with server part
 * @return object mo info object, it generated on server in MatrixCalculator::GetMoInfo()
 */
matrix.calculator.get_mo_info = function(mo_id, period_start, period_end) {
	return matrix.mo_info;
}

/**
 * Calculate task parameters - should be used for task or kpi_task indicators type only
 * @param numeric id indicator to mo ID
 * @param object type type of calculation - fact, plan, etc.
 * @param string period_start period start
 * @param string period_end period end
 * @return numeric parameter value
 */
matrix.calculator.calculate_task_param = function(id, type, period_start, period_end) {
	// For now tasks is calculated on server side at first, not in client calculator
	return matrix.calculator.data[id][type['key']];
	var behaviour_key = matrix.calculator.data[id]['indicator_behaviour_key'];
	var result = null;
	switch (type['key']) {
		case 'weight':
			if(behaviour_key == 'kpi_task') {
				result = matrix.calculator.data[id]['weight'];
			}
			else {
				var childs = matrix.calculator.get_childs(id, period_start, period_end);

				if(!childs.length) {
					result = 0;
					var fact_data = matrix.calculator.get_task_facts(
						id,
						period_start,
						period_end
					);
					var exists = false;
					
					if (fact_data) {
						for( var i in fact_data) {
							var item = fact_data[i];
							if(parseInt(item['value']) == 1) {
								exists = true;
								var add_data = 0;
								if(parseInt(matrix.calculator.data[id]['standard_operation_time'])) {
									var history = matrix.calculator.get_history(id, period_end);
									add_data = parseFloat(item['complexity']) * parseFloat(history['standard_operation_time']);
									if(!isNaN(add_data))
										result += add_data;
								}
								else {
									add_data = parseFloat(item['complexity']);
									if(!isNaN(add_data))
										result += add_data;
								}
							}
						}
					}
				}
				else {
					result = 0;
					var fact = matrix.calculator.get_param(
						id, 
						matrix.calculator.expression_types_by_key['fact'],
						period_start, 
						period_end
					);

					if(fact == 1 || matrix.task_nogroup_calculation) {
						for(var i in childs) {
							var child_id = childs[i];
							result += matrix.calculator.get_param(
								child_id, 
								matrix.calculator.expression_types_by_key['weight'],
								period_start, 
								period_end
							);
						}
					}
				}
			}
			break;
		case 'fact':
			if(behaviour_key == 'kpi_task') {
				result = 0;
				var childs = matrix.calculator.get_childs(id, period_start, period_end);
				for(var i in childs) {
					var child_id = childs[i];
					var weight = matrix.calculator.get_param(
						child_id, 
						matrix.calculator.expression_types_by_key['weight'],
						period_start,
						period_end
					);
					if(!isNaN(weight))
						result += weight;
					
				}
				result /= 60;
				
			}
			else {
				if(Date.fromString(period_start).getTime() > Date.fromString(matrix.calculator.data[ id ]['live_start']).getTime()) {
					period_start = matrix.calculator.data[ id ]['live_start'];
				}
				fact_data = matrix.calculator.get_task_facts(
					id,
					period_start, 
					period_end
				);
				last_fact = fact_data.pop();
				result = last_fact ? parseInt(last_fact['value']) : null;
			}
			break;
		case 'plan':
			if(behaviour_key != 'kpi_task') {
				if(parseFloat(matrix.calculator.data[ id ]['plan_default']) > 0) {
					if(parseInt(matrix.calculator.data[ id ]['standard_operation_time'])) {
						result = parseFloat(matrix.calculator.data[ id ]['plan_default']) * parseFloat(matrix.calculator.data[ id ]['standard_operation_time']);
					}
					else {
						result = parseFloat(matrix.calculator.data[ id ]['plan_default']);
					}
				}
				else {
					// Sum
					result = 0;
					var childs = matrix.calculator.get_childs(id, period_start, period_end);
					for(var i in childs) {
						var child_id = childs[i];
						var plan = matrix.calculator.get_param(
							child_id, 
							matrix.calculator.expression_types_by_key['plan'],
							period_start, 
							period_end
						);
						result += parseFloat(plan);
					}
				}
			}
			break;
		case 'res':
			if(behaviour_key != 'kpi_task') {
				var fact = matrix.calculator.get_param(
					id, 
					matrix.calculator.expression_types_by_key['fact'],
					period_start, 
					period_end
				);
				result = fact == 1 ? 100 : 0;
			}
			break;
		case 'complexity':
			var childs = matrix.calculator.get_childs(id, period_start, period_end);
			if(behaviour_key != 'kpi_task'
				&& !childs.length
			) {
				// Calculate unknown parameter value
				result = 0;
				var fact_data = matrix.calculator.get_task_facts(
					id,
					period_start,
					period_end
				);

				var exists = false;
				if (fact_data) {
					// Sum
					for(var i in fact_data) {
						var item = fact_data[i];
						if(item['value'] != 1) {
							exists = true;
							if(parseInt(matrix.calculator.data[ id ]['standard_operation_time'])) {
								var history = matrix.calculator.get_history(id, period_end);
								result += parseInt(item['complexity']) * parseFloat(history['standard_operation_time']);
							}
							else {
								result += parseFloat(item['complexity']);
							}
						}
					}
				}
				if (!exists) {
					result = '~';
				}
			}
			else {
				// Sum
				result = 0;
				for(var i in childs) {
					var child_id = childs[i];
					var complexity = matrix.calculator.get_param(
						child_id, 
						matrix.calculator.expression_types_by_key['complexity'],
						period_start, 
						period_end
					);
					result += parseFloat(complexity);
				}
			}
			break;
		case 'mark':
			if(behaviour_key == 'kpi_task') {
				// Average mark for all tasks
				result		= 0;
				var count	= 0;
				var childs	= matrix.calculator.get_childs(id, period_start, period_end);
				for(var i in childs) {
					var child_id = childs[i];
					var mark = matrix.calculator.get_param(
						child_id, 
						matrix.calculator.expression_types_by_key['mark'],
						period_start, 
						period_end
					);
					if(mark !== '~') {
						result += parseFloat(mark);
						count++;
					}
				}
				if(count) {
					result /= count;
				}
			}
			else {
				var fact = matrix.calculator.get_param(
					id, 
					matrix.calculator.expression_types_by_key['fact'],
					period_start, 
					period_end
				);
				//if(fact == 1) { // Ok
					var fact_data = matrix.calculator.get_task_facts(
						id,
						period_start,
						period_end
					);
					switch (matrix.task_mark_calculation_type) {
						case 'last':
							// Last mark
							var last_fact = array_pop(fact_data);
							if(last_fact['mark'])
								result = parseInt(last_fact['mark'])
							else if(fact == 1)
								result = matrix.task_default_mark;
							else
								result = 0;
							break;

						case 'mean':
							// Mean mark value
							var count = 0;
							for(var i in fact_data) {
								var fact_item = fact_data[i];
								if(fact_item['mark'] !== null) {
									count++;
									result += parseInt(fact_item['mark']);
								}
							}
							if(count)
								result = result / count;
							else if(fact == 1)
								result = matrix.task_default_mark;
							else
								result = 0;
							break;

						default:
							break;
					}
				/*}
				else {
					result = '~';
				}*/
			}
			break;
		case 'cost':	
			var mo_id		= matrix.calculator.get_mo_id(id, period_start, period_end);
			var mo_info		= matrix.calculator.get_mo_info(mo_id, period_start, period_end);
			if(behaviour_key == 'kpi_task') {
				result	= 0;
				var childs = matrix.calculator.get_childs(id, period_start, period_end);
				for(var i in childs) {
					var child_id = childs[i];
					var cost = matrix.calculator.get_param(
						child_id, 
						matrix.calculator.expression_types_by_key['cost'],
						period_start, 
						period_end
					);
					if(cost !== '~') {
						result += parseFloat(cost);
					}
				}
			}
			else {
				var fact = matrix.calculator.get_param(
					id, 
					matrix.calculator.expression_types_by_key['fact'],
					period_start, 
					period_end
				);
				var cost_key = fact == 1 ? 'weight' : 'plan';
				var minutes = matrix.calculator.get_param(
					id, 
					matrix.calculator.expression_types_by_key[ cost_key ],
					period_start, 
					period_end
				);
				result = parseFloat(mo_info['tasks_hour_cost']) * parseFloat(minutes) / 60;
			}
			break;
		case 'cost_with_weight':	
			var mo_id		= matrix.calculator.get_mo_id(id, period_start, period_end);
			var mo_info		= matrix.calculator.get_mo_info(mo_id, period_start, period_end);
			if(behaviour_key == 'kpi_task') {
				result	= 0;
				var childs = matrix.calculator.get_childs(id, period_start, period_end);
				for(var i in childs) {
					var child_id = childs[i];
					var cost_with_weight = matrix.calculator.get_param(
						child_id, 
						matrix.calculator.expression_types_by_key['cost_with_weight'],
						period_start, 
						period_end
					);
					if(cost_with_weight !== '~') {
						result += parseFloat(cost_with_weight);
					}
				}
			}
			else {
				var cost = matrix.calculator.get_param(
					id, 
					matrix.calculator.expression_types_by_key['cost'],
					period_start, 
					period_end
				);
				if(mo_info['tasks_weight']) {
					result = parseFloat(cost) * parseFloat(mo_info['tasks_weight']) / 100;
				}
			}
			break;
		default:
			break;
	}

	return result;
}

/**
 * Calculate parameters for indicator
 * @param numeric id indicator to mo ID
 * @param object type type of calculation - fact, plan, etc.
 * @param string period_start period start
 * @param string period_end period end
 * @param bool is_final is this last calculation, or we should retrive period segments for this indicator
 * @return numeric parameter value
 */
matrix.calculator.get_param = function(
	id,
	type,
	period_start,
	period_end,
	is_final
) {
	var result = matrix.calculator.get_cache_value(id, type, period_start, period_end);
	if(result === null) {
		// Get indicator data if no exists

		// Calculate value if data
		if(matrix.calculator.data[ id ]) {
			if(matrix.calculator.data[ id ]['indicator_behaviour_key'] == 'task'
				|| (matrix.calculator.data[ id ]['indicator_behaviour_key'] == 'kpi_task'
					//&& !in_array(type['key'], ['plan', 'res'])
				)
			) {
				// Tasks quick calc
				result = matrix.calculator.calculate_task_param(id, type, period_start, period_end);
			}
			else if(in_array(type['key'], ['complexity', 'mark', 'cost', 'cost_with_weight'])) {
				result = null;
			}
			else if(is_final) {					
				var show_past
				if(typeof(matrix.show_past_plans_and_facts) == 'undefined' 
					|| matrix.show_past_plans_and_facts == '0')
					show_past = false;
				else
					show_past = true;
				// One segment value	
				if(show_past == false
					&& in_array(matrix.calculator.data[ id ]['indicator_behaviour_key'], ['pay', 'kpi_pay'])
					&& in_array(type['key'], ['plan','fact'])){
					var pay_plan = true;
					result = matrix.calculator.calculate_final_param(id, type, period_start, period_end, pay_plan);
				}
				else				
					result = matrix.calculator.calculate_final_param(id, type, period_start, period_end);
			}
			else {
				// Sub-periods values
				result = matrix.calculator.calculate_segments(id, type, period_start, period_end);
			}
		}
		else {
			// If no indicator data
			result = '#';
		}

		// Save
		matrix.calculator.set_cache_value(id, type, period_start, period_end, result);
	}
	return result;
}

/**
 * Retrive history record for indicator
 * @param numeric id indicator to mo ID
 * @param mixed till date till history shoud be retrived, can be string 'dd.mm.yyyy' or date object
 * @return object object with history data 
 */
matrix.calculator.get_history = function(id, till) {
	if(typeof(till) == 'string')
		till = Date.fromString(till);
	for(var i in matrix.calculator.data[ id ]['history']) {
		var item = matrix.calculator.data[ id ]['history'][ i ]
		,	item_period_end = Date.fromString(item['period_end']);
		if(item_period_end.more(till)) {
			return item;
		}
	}
}

/**
 * Parse string into numeric value
 * @param string str base string, for example 'F123' or 'P456'
 * @return numeric parsed ID, for example 123 or 456
 */
matrix.calculator.get_id_from_string = function(str) {
	return str.replace(/[^\d]+/, '');
}

/**
 * Calculate final parameters for indicator - here is no date segments division, just calculation
 * @param numeric id indicator to mo ID
 * @param object type type of calculation - fact, plan, etc.
 * @param string period_start period start
 * @param string period_end period end
 * @return numeric parameter value
 */
matrix.calculator.calculate_final_param = function(
	id,
	type,
	period_start,
	period_end,
	pay_plan
) {
	var history = matrix.calculator.get_history(id, period_end)
	,	result = null;
	// Calculate	
	if (history 
		&& typeof(history[ type['key']+ '_calculation' ]) != 'undefined'
		&& $.trim( history[ type['key']+ '_calculation' ] ) !== ''
	) {
		// Parameter is complex and calculates by formula
		var calculation = ' '+ history[ type['key']+ '_calculation' ]+ ' '
		,	dependences = $.trim( history[ type['key']+ '_dependences' ] );
		// Check function params
		var arr = dependences ? dependences.split(' ') : [];

		for( var i in arr) {
			var dep = arr[ i ]
			,	dep_id	= matrix.calculator.get_id_from_string(dep)
			,	dep_type = typeof(matrix.calculator.expression_types_by_char[ dep[0] ]) != 'undefined'
				? matrix.calculator.expression_types_by_char[ dep[0] ]
				: ''
			,	dep_value = matrix.calculator.get_param(
					dep_id,
					dep_type,
					period_start,
					period_end
			);

			if (dep_value !== null) {
				// Twice
				for (var i = 0; i < 2; i++) {
					calculation = str_replace(" "+ dep+ " ", " "+ dep_value+ " ", calculation);
				}
			}
		}
		calculation = $.trim(calculation, period_start, period_end);
		try {
			result = matrix.expression.calculate(calculation, matrix.calculator.data[ id ]);
		}
		catch (e) {
			result = 0;
		}
	}
	else {
		// Parameter is simple
		switch (type['key']) {
			case 'weight':
				result = history['weight'];
				break;
			case 'fact':
				// Periodic counting
				var show_facts	= true;
				if(parseInt(matrix.calculator.data[id]['plan_periodic_counting'])) {
					var included			= 0;
					var result				= 0;
					var moment				= period_start;
					var period_end_time		= matrix.period.make_time(period_end);
					do {
						var subperiod = matrix.period.get_period_for_key(
							history['plan_period_key'],
							moment
						);
						var subperiod_end_time = matrix.period.make_time(
							subperiod['period_end']
						);
						if(period_end_time >= subperiod_end_time) {
							// All subperiod facts
							if(included == 0) {
								period_start = subperiod['period_start'];
							}
							period_end = subperiod['period_end'];
							included++;
						}
						var next_start_time = subperiod_end_time + DAY_TIME;
						moment	= (new Date(next_start_time * 1000)).asString();//$this->sys->GetDateTime($next_start_time);
					}
					while (subperiod_end_time <= period_end_time);
					show_facts = included > 0;
				}
				if(show_facts) {
					var fact_data = matrix.fact.extract_facts_values_list(
						matrix.calculator.data[ id ].facts_list,
						period_start,
						period_end,
						1,
						false,
						'value',
						pay_plan
					);

					if (fact_data.length) {
						result = matrix.calculation.calculate(
							matrix.calculator.data[ id ]['indicator_calculation_key'],
							fact_data
						);
					}
					else {
						fact_data = matrix.fact.extract_facts_values_list(
							matrix.calculator.data[ id ].facts_list,
							period_start,
							period_end
						);
						result = fact_data.length ? '~' : '#';
					}
				}
				break;
			case 'plan':
				var period_start_time	= matrix.period.make_time(period_start);
				var period_end_time		= matrix.period.make_time(period_end);
				var moment				= period_start;
				var num					= 0;
				var sum					= 0;
				var result				= null;
				do {
					var subperiod = matrix.period.get_period_for_key(
						history['plan_period_key'],
						moment
					);
					var subperiod_start_time = matrix.period.make_time(
						subperiod['period_start']
					);
					var subperiod_end_time = matrix.period.make_time(
						subperiod['period_end']
					);

					if(Date.fromString(moment).more(Date.fromString(matrix.calculator.data[ id ].live_start_real))	// _real is because on servers side live_start and live_end substituded with execution_start and execution_end
						&& Date.fromString(moment).less(Date.fromString(matrix.calculator.data[ id ].live_end_real))
					) {	
						var fact_data = matrix.fact.extract_plans_values_list(
							matrix.calculator.data[ id ].facts_list,
							subperiod['period_start'],
							subperiod['period_end'],
							1,
							false,
							'value',
							pay_plan
						);
						
						if (fact_data.length) {
							sum = matrix.calculation.calculate(
								matrix.calculator.data[ id ].indicator_calculation_key,
								fact_data
							);
						}
						else {
							fact_data = matrix.fact.extract_plans_values_list(
								matrix.calculator.data[ id ].facts_list,
								subperiod['period_start'],
								subperiod['period_end']
							);
							sum = fact_data.length ? '~' : '#';
						}

						var delta = (
							Math.min(period_end_time + DAY_TIME - 1, subperiod_end_time + DAY_TIME - 1) -
							Math.max(period_start_time, subperiod_start_time)
						) / (
							subperiod_end_time + DAY_TIME - 1 - subperiod_start_time
						);
						
						// Periodic counting
						if(parseInt(matrix.calculator.data[ id ].plan_periodic_counting)) {
							delta = period_end_time >= subperiod_end_time ? 1 : 0;
						}

						switch (matrix.calculator.data[ id ].indicator_calculation_key) {
							case 'summ':
								if (sum !== '~' && sum !== '#') {
									if (result !== null) {
										result += sum * delta;
									}
									else {
										result = sum * delta;
									}
								}
								break;
							case 'mean':
								if (sum !== '~' && sum !== '#') {
									num += delta;
									if (result !== null) {
										result += sum * delta;
									}
									else {
										result = sum * delta;
									}
								}
								break;
							case 'simple':
								break;
						}
					}
					next_start_time = subperiod_end_time + DAY_TIME;
					moment	= new Date(next_start_time * 1000);//$this->sys->GetDateTime($next_start_time);
				}
				while (subperiod_end_time < period_end_time);
				
				switch (matrix.calculator.data[ id ].indicator_calculation_key) {
					case 'mean':
						if (num) {
							result = result / num;
						}
						break;
					case 'simple':
						if (matrix.calculator.data[ id ].indicator_behaviour_key === 'task' &&
							type['key'] === 'fact'
						) {
							subperiod['period_start'] = matrix.calculator.data[ id ].live_start_real;
						}
						var fact_data = matrix.fact.extract_plans_values_list(
							matrix.calculator.data[ id ].facts_list,
							subperiod['period_start'],
							subperiod['period_end'],
							1
						);
						
						if (fact_data) {
							result = matrix.calculation.calculate(
								matrix.calculator.data[ id ].indicator_calculation_key,
								fact_data
							);
						}
						else {
							fact_data = matrix.fact.extract_plans_values_list(
								matrix.calculator.data[ id ].facts_list,
								subperiod['period_start'],
								subperiod['period_end']
							);
							if (fact_data) {
								result = '~';		
							}
						}
						break;
				}
				if (result === null) {
					result = '#';
				}
				break;
			case 'res':
				result = '#';
				break;
		}
	}
	// For case if is a very small number
	return precision_number(result);
}

/**
 * Calculate date segments parameters for indicator. It will retrive all date segments for indicator in this period and calculate each of them.
 * @param numeric id indicator to mo ID
 * @param object type type of calculation - fact, plan, etc.
 * @param string period_start period start
 * @param string period_end period end
 * @return numeric parameter value
 */
matrix.calculator.calculate_segments = function(id, type, period_start, period_end) {
	var result		= null
	,	segments	= matrix.calculator.get_segments(id, type, period_start, period_end);
	switch (matrix.calculator.data[id]['indicator_calculation_key']) {
		case 'summ':
			for (var i in segments) {
				var segment = segments[i]
				,	sum = matrix.calculator.get_param(
					id,
					type,
					segment['period_start'],
					segment['period_end'],
					true
				);
				if (sum !== '~' && sum !== '#') {
					if (result === null) {
						result = 0;
					}
					sum = parseFloat(sum);
					if(isNaN(sum))
						sum = 0;
					result += sum;
				}
			}
			break;
		case 'mean':
			var time = 0;
			for(var i in segments) {
				var segment = segments[i]
				,	sum = matrix.calculator.get_param(
					id,
					type,
					segment['period_start'],
					segment['period_end'],
					true
				);
				if (sum !== '~' && sum !== '#') {
					
					var subperiod_time 
						= (segment['period_end'].getTime() / 1000)
						- (segment['period_start'].getTime() / 1000);
					if (result === null) {
						result = 0;
					}
					sum = parseFloat(sum);
					if(isNaN(sum))
						sum = 0;
					result += sum * subperiod_time;
					time += subperiod_time;
				}
			}
			
			if (typeof(result) != undefined && time) {
				result /= time;
			}
			else {
				result = '#';
			} 	
			break;
		case 'simple':
			if(segments.length) {
				var last_segment = segments[segments.length - 1];
				result = matrix.calculator.get_param(
					id,
					type,
					last_segment['period_start'],
					last_segment['period_end'],
					true
				);
			}
			break;
	}
	if (result === null) {
		result = '#';
	}
	return result;
}

/**
 * Retrive all date segments for indicator
 * @param numeric id indicator to mo ID
 * @param object type type of calculation - fact, plan, etc.
 * @param string period_start period start
 * @param string period_end period end
 * @return array data with all calculation segments for indicator in this period, will include for each element period_start and period_end values
 */
matrix.calculator.get_segments = function(id, type, period_start, period_end) {
	var segments			= []
	,	hst_start			= period_start
	,	hst_end				= null
	,	last_calculation	= null;

	for(var i in matrix.calculator.data[ id ]['history']) {
		var item = matrix.calculator.data[ id ]['history'][ i ]
		if(!item['period_start'] || !item['period_end']) continue;

		var	item_period_start = Date.fromString(item['period_start'])
		,	item_period_end = Date.fromString(item['period_end']);
		if(item_period_start.less(period_end)
			&& item_period_end.more(period_start)
		) {
			if(last_calculation !== null
				&& last_calculation !== item[ type['key']+ '_calculation' ]
			) {
				segments.push({
					period_start:	hst_start
				,	period_end:		hst_end
				});
				hst_start = item['period_start'];
			}
			last_calculation	= item[ type['key']+ '_calculation' ];
			hst_end				= item['period_end'];
		}
	}
	if(last_calculation !== null) {
		segments.push({
			period_start:	hst_start
		,	period_end:		period_end
		});
	}
	if(segments.length) {
		for(var i in segments) {
			if(typeof(segments[i].period_start) == 'string')
				segments[i].period_start = Date.fromString(segments[i].period_start);
			if(typeof(segments[i].period_end) == 'string')
				segments[i].period_end = Date.fromString(segments[i].period_end);
		}
	}
	return segments;
}
