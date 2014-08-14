function new_load_report_list(report_list) {
	var make_list = function(report_list) {
		$new_report_list.empty();
		$.each(report_list, function() {
			var report_id = this.report_id;
			$new_report_list.append(
				$('<a href="#"/>')
				.text(
					this['protected'] == 1 
						? get_from_dict('string', 'system_report_' + this.report_key)
						: this.name
				)
				.click(function() {
					$doc.click();
					show_loading_indicator();
					setTimeout(function() {
						if (!new_report) {
							ui.remove_reports();
							ui.desktop.add($.jwin({section: 'center'}));
							new_report = new NewReport();
						}
						new_report.open(report_id);
					}, 10);
					return false;
				})
			);
		});
	};
	
	if (!report_list)
		$.ajax({
			url: up + 'new_report/list'
		,	success: function(data) {
				make_response(data, function() {
					make_list(data.report_list);
				});
			}
		});
	else {
		make_list(report_list);
	}
}

function NewReport() {
	var task_statuses = get('task_status');
	var report_id = 0
	,	report_tbl = null
	,	report_name = ''
	,	tmp_report_name
	,	rep_name_commit_flag = 0
	,	rep_name_editing_mode = 0
    ,   cheked_params_list= [1,2,3,4]
	,	old_report_id = 0
	,	save_dlg = function(name, success) {
			// Name edit window
			var form = $('<div class="field_cont"><div class="field">' +
				'<label>' + get_from_dict('field', 'name') + '</label>' +
				'<input type="text" class="wMax" name="name" value="' + 
				name + '">' +
				'</div></div>');
			var save_btn = $('<input type="submit" class="btSave w80" value="' + 
				get_from_dict('button', 'save') + '" style="margin-top: 10px;">');
			save_btn.click(function() {
				var report_name = $.trim($('input[name=name]', form).val());
				if(success) {
					success(report_name);
					win.close();
				}
			});
			form.append(save_btn);
			var win = $.jwin({
				section:		'right',     
				title:			'rnedit',   
				help_link:		make_help_link('#'),   
				content:		form
			});
			ui.desktop.add(win);
		}
	,	$name_edit = $('<a href="#" class="btEdit" title="' + get_from_dict('button', 'edit') + '"></a>')
			.click(function() {
//				save_dlg(report_name, function(name) {
//					set_name(name);
					$name.click();
					$name_edit.hide();
			})
	,	$name_commit = $('<a href="#" class="btAccept" title="' + get_from_dict('button', 'ok') + '" style="display: none;"></a>')
			.click(function() {
				rep_name_commit_flag = 1;
				rep_name_editing_mode = 0;
				$('#report_name_edit').blur();
			})	
	,	$name_cancel = $('<a href="#" class="btCancel" title="' + get_from_dict('button', 'cancel') + '" style="display: none;"></a>')
			.click(function() {
				rep_name_commit_flag = 0;
				rep_name_editing_mode = 0;
				set_name(report_name);
				if (old_report_id != 0) {
					set_id(old_report_id);
					old_report_id = 0;
				}
				$('#report_name_edit').remove();
				$name_commit.hide();
				$name_cancel.hide();
				$name_edit.show();
			})			
	,	$name = $('<span style="color: #999999; font-size: 14px; font-weight: bold; margin: 10px"/>')
			.click(function() {
				$name_commit.show();
				$name_cancel.show();
				$name_edit.hide();
				if (!rep_name_editing_mode) {
					rep_name_editing_mode = 1;
					$(this).empty().append(
						$('<input type="text" id="report_name_edit" value="' + report_name + '" size="50" />')
							.keydown(function(e) {
								switch(e.which) {
									case 13: // Enter
										rep_name_editing_mode = 0;
										rep_name_commit_flag = 1;
										$(this).blur();
										break;
									case 27: // Esc
										$name_cancel.click();
										break;
								}
							})
							.blur(function() {
								if (rep_name_commit_flag) {
									this.value = $.trim(this.value);
									tmp_report_name = (this.value?this.value:report_name);
									check_sort();
									if (old_report_id == 0) {
										if (report_id > 0) {
											$.ajax({
												url: up + 'new_report/save'
											,	data: {
													report_id:			report_id
												,	name:				tmp_report_name
												,   sort_key:           sort_key
												,   sort_mode:          sort_mode
												}
											,	success: function() {
													set_name(tmp_report_name);
													new_load_report_list();
												}
											});	
										} else {
											set_name(tmp_report_name);
										}
									}
									else {
										set_name(tmp_report_name);
										save();
										old_report_id = 0;
									}
									$name_commit.hide();
									$name_cancel.hide();
									$name_edit.show();
									$(this).remove();
									$name.text(tmp_report_name);
									rep_name_commit_flag = 0;
								}
							})
					).children('input').select().focus();
				}
			})
	//,	$mo_list		= $('<div/>')
    ,   $mo_all         = $('<label for="' + uid.get(true) + '" />')
            .text(get_from_dict('string', 'all'))
            .prepend(
                $('<input id="' + uid.get() + '" name="" type="checkbox" />').click(function(e) {
                    $('INPUT:checkbox', $mo_list).attr('checked', false);
                    e.stopPropagation();
                })
            )
	,	$mo_list		= $.jMatrix({
			id:		'mo_list'
		,	'class':	'tbl'
		,	init:	function(data) {
				var cell = $(data.row.cells.checkbox);
				cell.css('padding', 0);
				cell.addClass('tac');
				$('<input id="report_mo_' + data.row_data.mo_id + 
					'" type="checkbox" value="' + data.row_data.mo_id + 
					'" style="margin: 1px" />')
					.appendTo(cell)
                    .click(function(e) {
                        if (this.checked) {
                            $('INPUT:checkbox', $mo_all).attr('checked', false);
                        }
                        e.stopPropagation();
                    });
			}
        ,   click:  function(e) {
                    var input = $('INPUT:checkbox', this);
                    input.attr('checked', !input.attr('checked'));                    
                }
		,	cols: {
				checkbox: 	{
					title:	get_from_dict('field', 'label')
                ,	html: $('<input type="checkbox" />').click(function(e) {
                        $('INPUT:checkbox', $mo_list).attr('checked', this.checked);
                        $('INPUT:checkbox', $mo_all).attr('checked', this.checked?false:true);
                        e.stopPropagation();
                    })
				}
			,	user_name:	{
					title:	get_from_dict('field', 'user')
				,	sort:	'user_name'
                ,	data:	{level: 'pid', value: 'user_name'}
				}
			,	name:	{
					title:	get_from_dict('field', 'object')
				,	sort:	'name'
				,	data:	{value: 'name'}
				,	hidden:	1
				}
			,	res:	{
					title:	'R'
				,	sort:	['res', 'number']
				,	'class':'number'
				,	data:	{value: 'res'}
				}
			}
		})
    ,   $indicator_all         = $('<label for="' + uid.get(true) + '" />')
            .text(get_from_dict('string', 'all'))
            .prepend(
                $('<input id="' + uid.get() + '" type="checkbox" />').click(function(e) {
                    $('INPUT:checkbox', $indicator_list).attr('checked', false);
                    e.stopPropagation();
                })
            )
	,	$indicator_list	= $.jMatrix({
			id:		'report_indicator_list'
		,	init:	function(data) {
				$('<input id="report_indicator_' + data.row_data.indicator_id + '" type="checkbox" value="' + data.row_data.indicator_id + '" />')
					.appendTo(data.row.cells.checkbox)
                    .click(function(e) {
                        if (this.checked) {
                            $('INPUT:checkbox', $indicator_all).attr('checked', false);
                        }
                        e.stopPropagation();
                    });
			}
		,   click:  function(e) {
                    var input = $('INPUT:checkbox', this);
                    input.attr('checked', !input.attr('checked'));                    
                }
        ,	cols: {
				checkbox: 	{
					title:	get_from_dict('field', 'label')
                ,   html: $('<input type="checkbox" />').click(function(e) {
                        $('INPUT:checkbox', $indicator_list).attr('checked', this.checked);
                        $('INPUT:checkbox', $indicator_all).attr('checked', this.checked?false:true);
                        e.stopPropagation();
                    })
				,	data: {'class': 'class'}
				}
			,	name:	{
					title:	get_from_dict('field', 'name')
				,	sort:	'name'
				,	data:	{level: 'pid', value: 'name'}
				}
			}
		})
	,   $field_list     = $('<div class="cb_list"/>')
    ,   $option_list    = $('<div class="cb_list"/>')
    ,   sort_key        = null
    ,   sort_mode       = null
    ,	$params         = $('<div style="margin:10px"/>')
            .append($('<div class="info"/>')
                .append('<h6>' + get_from_dict('string', 'reportFieldList') + '</h6>')
                .append($field_list)
                .append('<div class="cb"/>')
            )
            .append($('<div class="info"/>')
                .append('<h6>' + get_from_dict('string', 'reportOptionList') + '</h6>')
                .append($option_list)
                .append('<div class="cb"/>')
				.append('<form id="download" method="POST"/>')
            )
    ,	$mo_win = $.jwin({
			section:		'left'
		//,	collapsed:		true
		,	closable:		false
		,	maximizable:	false
		,	title:			get_from_dict('string', 'reportMoList')
		,	layout:			't'
		,	top_bar:		$mo_all
		,	content:		$mo_list
		,	on_close:		function(win) {
				$mo_list.remove();
				$.jwinDefaults.on_close(win);
			}
		})
	,	$indicator_win = $.jwin({
			section:		'left'
		,	collapsed:		true
		,	closable:		false
		,	maximizable:	false
		,	spread_width:	400
		,	title:			get_from_dict('string', 'reportIndicatorList')
		,	layout:			't'
		,	top_bar:		$indicator_all
		,	content:		$indicator_list
		,	on_close:		function(win) {
				$indicator_list.remove();
				$.jwinDefaults.on_close(win);
			}
		})
	,	$params_win = $.jwin({
			section:		'left'
		,	collapsed:		true
		,	closable:		false
		,	maximizable:	false
		,	title:			get_from_dict('string', 'reportParamsList')
		,	content:		$params
		,	on_close:		function(win) {
                $field_list.remove();
				$option_list.remove();
				$.jwinDefaults.on_close(win);
			}
		})
    ,	$bt_save_copy = $('<input type="button" value="' + get_from_dict('button', 'saveAs') + '" disabled="disabled"/>').click(function() {
//			save_dlg('', function(name) {
//				set_id(0);
//				set_name(name);
//				save();
//			});
			old_report_id = report_id;
			set_id(0);
			$name.click();
			$name_edit.hide();
		})
	,	$bt_del = $('<input type="button" value="' + get_from_dict('button', 'del') + '" disabled="disabled"/>').click(function() {
			del();
		})
	,	$bt_excel = $('<input type="button" value="' + get_from_dict('button', 'saveToExcel') + '" />').click(function() {
			display(false, true);
		})
	,	$bt_csv = $('<input type="button" value="' + get_from_dict('button', 'export_to_csv') + '" />').click(function() {
			export_to_csv();
		})
	,	$bt_refresh = $('<input type="button" value="' + get_from_dict('button', 'refresh') + '"/>').click(function() {
			display(true);
		})
	,	$bt_save = $('<input type="button" value="' + get_from_dict('button', 'save') + '"/>').click(function() {
			save();
		});
		
	var null_jmatrix =  $.jMatrix({id: 'null_matrix'});
			
	// Switch to report mo list
	ui.mo_win.hide();
	ui.mo_win.parent().append($mo_win);
	
	// Add rows
	var rows = get('mo_list').get_rows();
	var accessible_mo_list = [];
	for (var i in rows) {
		var item = rows[i].data;
		if(item.mo_id == sys.auth_mo.mo_id
			|| item.pid == sys.auth_mo.mo_id
			|| allow('user_mo_all', item.mo_id)
		) {
			accessible_mo_list.push(item);
		}
	}
	$mo_list.paste(accessible_mo_list, 'mo_id', true);
	$mo_list.sort('name');
	
	// Get childs
	var mo_childs = {};
	var mo_rows = $mo_list.get_rows();
	for(i in mo_rows) {
		var pid = mo_rows[i].data.pid;
		if(!mo_childs[pid]) {
			mo_childs[pid] = [];
		}
		mo_childs[pid].push(mo_rows[i]);
	}
	
	var top_bar = [];
	if(allow('reports_write')) {
		top_bar.push($bt_refresh);
		top_bar.push($bt_save);
		top_bar.push($bt_save_copy);
	}
	top_bar.push($bt_excel);
	top_bar.push($bt_csv);
	if(allow('reports_write')) {
		top_bar.push($bt_del);
	}
	
	var kpi_task_matrix_cols = {
		indicator_to_mo_id: {
			title:		get_from_dict('field', 'id'),   
			sort:		['indicator_to_mo_id', 'number'],
			data:		{value: 'indicator_to_mo_id'},
			hidden: 1
		},  
		status: {
			title:    get_from_dict('string', 'status'),
			sort:    'status',
			data:    {value: 'status'}
		},
		name:    {
			title:    get_from_dict('field', 'name'),
			sort:    'name',
			data:    {level: 'pid', value: 'name'}
		},
		author:    {
			title:    get_from_dict('field', 'author'),
			sort:    'author',
			data:    {value: 'author'}
		},
		plan: {
			title:	get_from_dict('field', 'plan'),   
			'class':'number ar',
			sort:	['plan', 'number'],
			data:   {value: 'plan'}
		},	
		fact: {
			title:  get_from_dict('field', 'fact'),
			'class':'number ar',
			sort:  ['fact', 'number'],
			data:  {value: 'complexity'}
		},
		accepted: {
			title:  get_from_dict('field', 'accepted'),
			'class':'number ar',
			sort:  ['fact', 'number'],
			data:  {value: 'weight'}
		},
		period_start: {
			title:	get_from_dict('field', 'from'),
		//	hidden:	true,
			'class':'number',
			sort:	['live_start','date'],
			data:	{value:	'live_start'},
		},
		period_end: {
			title:	get_from_dict('field', 'to'),
		//	hidden:	true,
			'class':'number',
			sort:	['live_end','date'],
			data:	{value:	'live_end'}	
		},
	};
	
	var	$report_win = $.jwin({
			section:		'center'
		,	title:			''
		,	layout:			't'
		,	top_bar:		top_bar
		,	content:		$('<center/>').append($name).append($name_edit).append($name_commit).append($name_cancel)
		,	spread_width:	600
		,	init:			function() {}
		,	on_close:		function() {
				$mo_win.remove();
				ui.mo_win.show();
				get('mo_list').restore_closed();
				$indicator_win.close();
				$params_win.close();
				report = null;
			}
		,	extra_buttons: [
				{
					'class':	'jwin_bt_csv',
					'function':	function() {
						export_to_csv();
					},
					title:		get_from_dict('button', 'export_to_csv')
				},
			]
		})
		
	,	init = function() {
			$.ajax({
				url: up + 'new_report/get_params'
			,	async: false
			,	success: function(data) {
					make_response(data, function() {
						var i, j, l = data.mo_list.length
						,   id;
						
						$indicator_list.paste(data.indicator_list, 'indicator_id', true);
						
						l = data.field_list.length;
						id = 'report_field_';
                        for (i = 0; i < l; i++) {
                            $('<input id="' + id + data.field_list[i].report_field_id + '" type="checkbox" ' + (in_array(data.field_list[i].report_field_id, cheked_params_list)  ? 'checked' : '') + ' value="' + data.field_list[i].report_field_id + '" />')
                                .appendTo($field_list)
								.wrap('<label for="' + id  + data.field_list[i].report_field_id + '"></label>')
								.after(' ' + get_from_dict('reportField', data.field_list[i].key));
						}
						l = data.option_list.length;
						id = 'report_option_';
						for (i = 0; i < l; i++) {
							if (data.option_list[i].key == 'period') {								
								$('<select data-id="'+data.option_list[i].report_option_id+'" style="margin: 1px 5px 0 5px; width: 90%" id="' + id + data.option_list[i].report_option_id + '"/>') /*value="' + data.option_list[i].report_option_id + '"*/
									.each(function() {
										var period_list = get('indicator_period')
										, item = $(this);
										for (var j in period_list) {
										    if (period_list.hasOwnProperty(j)) {
										       item.append('<option value="' + period_list[j].key + '">' + get_from_dict('period', period_list[j].key) + '</option>');
										    }
										}
									})
									.appendTo($option_list);
							}
							else {
								$('<input id="' + id + data.option_list[i].report_option_id + '" type="checkbox" value="' + data.option_list[i].report_option_id + '" />')
									.appendTo($option_list)
									.wrap('<label for="' + id + data.option_list[i].report_option_id + '"></label>')
									.after(' ' + get_from_dict('reportOption', data.option_list[i].key));
							}
						}
						set_id(report_id);
						set_name(report_name);
                        set_sort(sort_key, sort_mode);
					});
				}
			});
			ui.desktop
				.add([
					$indicator_win
				,	$params_win
                ,   $report_win
				]);
		}
	,	set_id = function(new_id) {
	    	if (!new_id) {
	    		new_id = 0;
	    	}
	    	report_id = new_id;
	    	if (new_id) {
	    		$bt_save_copy.removeAttr('disabled');
	    		$bt_del.removeAttr('disabled');
	    	}
	    	else {
	    		$bt_save_copy.attr('disabled', 'disabled');
	    		$bt_del.attr('disabled', 'disabled');	    		
	    	}
	    	return false;
		}
	,	set_name = function(new_name) {
	    	if (!new_name) {
	    		new_name = get_from_dict('string', 'newReportTitle');
                report_name = new_name;
	    	}
            else {
                report_name = new_name;
                new_name += ', ' + period.to_str();
            }
	    	$report_win.title(new_name);
	    	$name.text(new_name);
            return false;
		}
    ,   set_sort = function(key, mode) {
            sort_key  = key || 'user_name';
            sort_mode = mode || 0;
        }
	,	open = function(report_id) {
			if (report_id) {
				$.ajax({
					url: up + 'new_report/load',
					data: {report_id: report_id},
					success: function(data) {
						set_id(data.report.report_id);
						set_name(data.report.name);
						set_sort(data.report.sort_key, data.report.sort_mode);
                        if (report_tbl) {
							report_tbl.remove();
						}
						
						// Mo
						var i;
						for(i in data.report.mo) {
							var match	= data.report.mo[i].match(/(\d+)([+-])?/);
							var id		= match[1];
							var sign	= match[2];
							if(sign) {
								// Check childs
								$.each(mo_childs[id], function(i, item) { 
									item.$.find(':checkbox').attr('checked', 'checked');
								});
							}
							if(sign != '-' && mo_rows[id]) {
								// Check self
								mo_rows[id].$.find(':checkbox').attr('checked', 'checked');
							}
						}

						$('input', $mo_all).attr('checked', ($.inArray('0', data.report.mo) == -1?'':'checked'));
                        $indicator_list.find(':checkbox').each(function() {
							this.checked = $.inArray(this.value + '', data.report.indicator) == -1?'':'checked';
						});
                        $('input', $indicator_all).attr('checked', ($.inArray('0', data.report.indicator) == -1?'':'checked'));
						$field_list.find(':checkbox').each(function() {
							this.checked = $.inArray(this.value + '', data.report.field) == -1?'':'checked';
						});
						$option_list.find(':checkbox').each(function() {
							this.checked = data.report.options.hasOwnProperty(this.value);
							//$.inArray(this.value + '', data.report.options) == -1?'':'checked';
						});
						$option_list.find('select,INPUT[type!="checkbox"]').each(function(){
							$(this).val(data.report.options[$(this).attr('data-id')]);
							//option_list[$(this).attr('data-id')] = $(this).val();
							//option_list.push({name: $(this).attr('data-id'), value: $(this).val()});
						});
						setTimeout(function() {
							show_loading_indicator();
							display();
						}, 1000);
					}
				});
			}
			return false;
		}
	,	display = function(show_messages, excel) {
		    var mo_list = $('TBODY :checked', $mo_list).map(function() {
				return this.value;
			}).get()
			,	mo_all = $('INPUT', $mo_all).attr('checked')?1:0
			,	indicator_list = $('TBODY :checked', $indicator_list).map(function() {
					return this.value;
				}).get()
			,	indicator_all = $('INPUT', $indicator_all).attr('checked')?1:0
			,	field_list = $(':checked', $field_list).map(function() {
					return this.value;
				}).get()
			,	option_list = [];
			$('INPUT:checked', $option_list).each(function() {
				option_list.push({name: this.value, value: this.value});
				//[this.value] = this.value;
			});
			$('select,INPUT[type!="checkbox"]', $option_list).each(function(){
				//option_list[$(this).attr('data-id')] = $(this).val();
				option_list.push({name: $(this).attr('data-id'), value: $(this).val()});
			});
			/*,	option_list = $(':checked', $option_list).map(function() {
				    return this.value;
			    }).get();		*/	
			if ((mo_list.length || mo_all)
            &&  (indicator_list.length || indicator_all)
            &&  field_list.length
            ) {
				var data = {
							report_id:          report_id
						,   'mo_list[]':        mo_list
						,   mo_all:             mo_all
						,   'indicator_list[]': indicator_list
						,   indicator_all:      indicator_all
						,   'field_list[]':     field_list
						,   'option_list':    $.param(option_list)
						//,   'option_list[]':    option_list
					};
					
				if(excel) {
					// Try to create
					$.ajax({
						url: up + 'new_report/get_data?excel=1',
						data: data,
						success: function(data) {
							make_response(data, function() {
								// Download if no errors
								$('#download').ajaxForm({
									url: up + 'new_report/get_report',
									forceSync:     true,
									target:	'#download',
									iframe: true,
								}).submit();
								$.event.trigger("ajaxStop");
								$('#download').removeAttr('busy').css('opacity', '1');
							});
						}
					});
				}
				else {
					$.ajax({
						url: up + 'new_report/get_data'
					,	data: data
					,	success: function(data) {
							var i
							,   field_cols
							,   cols = {
									user_name:	{
										title:	get_from_dict('field', 'user')
									,	sort:	'user_name'
									,	data:	{value: 'full_name'}
									,   'class':'nowrap'
									,	click: allow('user_mo_read')
											?function(event) {
												load_user_to_mo_show({mo_id: event.data.row_id});
											}
											:function() {}
									},
									name:	{
										title:	get_from_dict('field', 'object')
									,	sort:	'name'
									,	data:	{value: 'name'}
									,	hidden:	1
									,	click: allow('user_mo_read')
											?function(event) {
												//mo.open(event.data.row_id);
											}
											:function() {}
									}
							};
							/*if (data.use_period) {
								cols.period = {
									title:    get_from_dict('reportField', 'period'),
									sort:	'period_f',
									empty_class:null,
									data:    {value: 'period_f'}
								};
							}*/
							if (!data.option_list['1']) {
								cols.user_name.data.level = 'pid';
							}
							
							var row_over_class = 'over';
							
							$.each(data.indicator_list, function(i, item) {
								var cellClick = function(e, type) {
									e.stopPropagation();
									if (e.data.row_data['indicator_to_mo_id' + i] && i > 0) {
										var options = {
											params: {indicator_to_mo_id: e.data.row_data['indicator_to_mo_id' + i]},
											matrix_obj: null_jmatrix
										};
										switch (e.data.row_data['indicator_behaviour_key' + i]) {
											case 'task':
												return load_indicator_to_mo_show_task(options);
											case 'standart':
												return load_indicator_to_mo_show_standart(options);
											case 'pay':
												return load_indicator_to_mo_show_pay(options);
											default:
												return load_indicator_to_mo_show(options);
										}

									}
								};
								var factClick = function(e) {
									e.stopPropagation();
									cellClick(e, 'fact');
								};
								var planClick = function(e) {
									e.stopPropagation();
									cellClick(e, 'plan');
								};
								field_cols = {};
								for (j in data.field_list) {
									if (data.field_list[j].key == 'struct')
										row_over_class = '';
									switch(data.field_list[j].key) {
										case 'fact':
											field_cols[data.field_list[j].key + i] = {
												title:    get_from_dict('reportField', data.field_list[j].key),
												empty_class:null,//'empty',
												'class':'number',
												sort:    [data.field_list[j].key + i, 'number'],
												data:    {
													'class': [data.field_list[j].key + '_expression' + i, 'counted'],
													hint: data.field_list[j].key + '_expression' + i,
													value: data.field_list[j].key + i
												},
												click:    factClick
											};
											break;
										case 'plan':
											field_cols[data.field_list[j].key + i] = {
												title:    get_from_dict('reportField', data.field_list[j].key),
												empty_class:null,//'empty',
												'class':'number',
												sort:    [data.field_list[j].key + i, 'number'],
												data:    {
													'class': [data.field_list[j].key + '_expression' + i, 'counted'],
													hint: data.field_list[j].key + '_expression' + i,
													value: data.field_list[j].key + i
												},
												click:    cellClick
											};
											break;
										case 'period':
										case 'weight':
										case 'res':
											field_cols[data.field_list[j].key + i] = {
												title:    get_from_dict('reportField', data.field_list[j].key),
												empty_class:null,//'empty',
												'class':'number',
												sort:    [data.field_list[j].key + i, 'number'],
												data:    {
													'class': [data.field_list[j].key + '_expression' + i, 'counted'],
													hint: data.field_list[j].key + '_expression' + i,
													value: data.field_list[j].key + i
												},
												click:    cellClick
											};
											break;
										case 'res_icon':
											field_cols[data.field_list[j].key + i] = {
												title:    '<img alt="" src="' + themeLink + 'traffic-light.png"/>',
												data:    {
													'class': data.field_list[j].key + i
												},
												click:    cellClick
											};
											break;
										case 'facts':
										case 'plans':
										case 'facts_rec':
										case 'plans_rec':
										case 'struct':
											field_cols[data.field_list[j].key + i] = {
												title:    get_from_dict('reportField', data.field_list[j].key),
												empty_class:null,//'empty',
												data:    {
													value: data.field_list[j].key + i
												},
												click:    cellClick
											};
											break;
										case 'res_img':
										case 'fact_img':
											field_cols[data.field_list[j].key + i] = {
												title:    get_from_dict('reportField', data.field_list[j].key),
												empty_class:null,//'empty',
												data:    {
													value: data.field_list[j].key + i
												},
												click:    cellClick
											};
											break;
										case 'missed':
											field_cols[data.field_list[j].key + i] = {
												title:    get_from_dict('reportField', data.field_list[j].key),
												empty_class:null,//'empty',
												'class':'tac',
												data:    {
													hint: get_from_dict('field', 'planFact'),
													value: data.field_list[j].key + i
												},
												click:    cellClick
											};
											break;
										case 'plan_responsible':
										case 'fact_responsible':
											var plan = data.field_list[j].key == 'plan_responsible' ? 'plan' : 'fact';
											field_cols[data.field_list[j].key + i] = {
												title:    get_from_dict('reportField', plan + '_responsible'),
												empty_class: null,
												'class': 'nowrap',
												sort:    [plan + '_responsible_mo_name' + i],
												data:    {
													value: plan + '_responsible_mo_name' + i
												},
												click:    allow('user_mo_read')
													? function(x) {
														return function(event){
															var key = x + '_responsible_mo_id' + i;
															if(key in event.data.row_data)
																load_user_to_mo_show({mo_id: event.data.row_data[key]});
														}
													} (plan)
													: function() {}
											};
											break;
									}
								}
								cols['indicator' + i] = {
									title:   item.name,
									cols:    field_cols
								};
								var opts = {
									title_text_top_to_bottom:		data.option_list['17'] !== undefined
									//title_text_90deg:		data.option_list['17'] !== undefined
								};
								for (j in data.mo_list) {
									data.mo_list[j] = prepare_data(data.mo_list[j], i, opts);
								}
								if (data.option_list['9']) {
									data.total = prepare_data(data.total, i, opts);
								}
								if (data.option_list['10']) {
									data.mean = prepare_data(data.mean, i, opts);
								}
							});
							for (i in data.mo_list) {
								data.mo_list[i] = prepare_mo_data(data.mo_list[i]);
							}
							if (report_tbl) {
								report_tbl.remove();
							}
							report_tbl = $.jMatrix({
								id:						'report'
							,	'class':				'tbl wa'
							,	row_over_class:			row_over_class
							,	sort_key:				sort_key
							,	sort_mode:				sort_mode
							//,	title_text_top_to_bottom:data.option_list['17'] !== undefined
							//,	title_text_90deg:		data.option_list['17'] !== undefined
							,	cols: 					cols
							,	data:					data.mo_list
							,	data_key:				'mo_id'
							});
							if (data.option_list['9']) {
								data.total.full_name = get_from_dict('field', 'total') + ':';
								report_tbl.paste([data.total], 'mo_id');
							}
							if (data.option_list['10']) {
								data.mean.full_name = get_from_dict('field', 'mean') + ':';
								report_tbl.paste([data.mean], 'mo_id');
							}
							report_tbl.css('margin-top', '10px');
							report_tbl.insertAfter($name.parent()).show();
							set_name(report_name);
						}
					});
				}
			}
			else {
				if (show_messages) {
					if (!mo_list.length && !mo_all) {
						c_alert(get_from_dict('string', 'reportMoListEmpty'));
					}
					else if (!indicator_list.length && !indicator_all) {
						c_alert(get_from_dict('string', 'reportIndicatorListEmpty'));
					}
					else if (!field_list.length) {
						c_alert(get_from_dict('string', 'reportFieldListEmpty'));
					}
                }
				else {
					if (report_tbl) {
						report_tbl.remove();
						report_tbl = null;
					}
				}
			}
			return false;
		}
    
    ,   check_sort = function() {
            if (report_tbl) {
                var sort = report_tbl.get_sort();
                sort_key = sort.key;
                sort_mode = sort.mode;
            }
        }
    
    ,   prepare_mo_data = function(item) {
            item.full_name = item.user_name;
            if (item.photo) {
                item.full_name += '<img class="photo" src="' + item.photo + '" alt="" />';
            }
            return item;
        }
	,	prepare_data = function(item, id, opts) {
			if ('indicator_to_mo_id' + id in item) {
				if ('res' + id in item) {
					if (item['res' + id] == '~') {
						item['res' + id] = '*';
					}
					else if (item['res' + id] == '#') {
						item['res' + id] = (item['indicator_behaviour_key' + id] == 'kpi_pay')
							?'&nbsp;'
							:'<span class="red">' + format_number(0, 2) + '</span>';
					}
					else if (item['res' + id] != '') {
					    item['res' + id] = format_number(item['res' + id], 2);
					}
					else {
						item['res' + id] = '-';
					}
				}
				if ('fact' + id in item) {
					if (item['indicator_behaviour_key' + id] == 'task') {
						item['fact' + id] = format_number((format_number(item['fact' + id]) == 1)?item['weight' + id]:0, 2);
					}
					else if (item['fact' + id] == '~') {
						item['fact' + id] = '*';
					}
					else if (item['fact' + id] == '#') {
						item['fact' + id] = item['fact_expression' + id]
							?'<span class="red">' + format_number(0, 2) + '</span>'
							:'<a href="#"><img alt="" class="db fr" src="' + themeLink + 'question-small.png"/></a>';
					}
					else if (item['fact' + id] != '') {
					    item['fact' + id] = format_number(item['fact' + id], 2);
					}
					else {
						item['fact' + id] = '-';
					}
				}
				if ('plan' + id in item) {
					if (item['indicator_behaviour_key' + id] == 'task') {
						item['plan' + id] = format_number(item['weight' + id], 2);
					}
					else if (item['plan' + id] == '~') {
						item['plan' + id] = '*';
					}
					else if (item['plan' + id] == '#') {
						if (item['indicator_behaviour_key' + id] == 'kpi_pay') {
							item['plan' + id] = '&nbsp;';
						}
						else {
							item['plan' + id] = item['plan_expression' + id]
								?'<span class="red">' + format_number(0, 2) + '</span>'
								:'<a href="#"><img alt="" class="db fr" src="' + themeLink + 'question-small.png"/></a>';
						}
					}
					else if (item['plan' + id] != '') {
					    item['plan' + id] = format_number(item['plan' + id], 2);
					}
					else {
						item['plan' + id] = '-';
					}
				}
				if ('weight' + id in item) {
					if (item['indicator_behaviour_key' + id] == 'task'
					||	item['indicator_behaviour_key' + id] == 'kpi_pay'
					||  item['pid' + id] == 0) {
						item['weight' + id] = '&nbsp;';
					}
					else {
						item['weight' + id] = item['weight' + id] == ''?'-':format_number(item['weight' + id]);
					}
				}
				if ('fact_data' + id in item) {
					var i = item['fact_data' + id].length, arr = [], j, vals;
					while (i--) {
						vals = [];
						for (j in item['fact_data' + id][i]['fields']) {
							vals.push(item['fact_data' + id][i]['fields'][j]);
						}
						arr.push([
							'<b>' + item['fact_data' + id][i]['fact_time'].substr(0, 10) + '</b>',
							'<b>' + item['fact_data' + id][i]['value'] + '</b>',
							vals.join(', ')
						].join(', '));
						
					}
					item['facts' + id] = arr.join('<br />');
				}
				if ('plan_data' + id in item) {
					var i = item['plan_data' + id].length, arr = [], j, vals;
					while (i--) {
						vals = [];
						for (j in item['plan_data' + id][i]['fields']) {
							vals.push(item['plan_data' + id][i]['fields'][j]);
						}
						arr.push([
							'<b>' + item['plan_data' + id][i]['fact_time'].substr(0, 10) + '</b>',
							'<b>' + item['plan_data' + id][i]['value'] + '</b>',
							vals.join(', ')
						].join(', '));
						
					}
					item['plans' + id] = arr.join('<br />');
				}
				if ('struct_data' + id in item) {
					if(item['indicator_behaviour_key' + id] == 'kpi_task') {
						var strust_item = item['struct_data' + id]
						if (strust_item.length > 0) {
							// Kpi-task indicators
							var struct = $('<div/>');
							item['struct' + id] = struct;
							var arr = [], str = []
								task_status = get('task_status'),
								status_info = [0, 0, 0, 0, 0, 0, 0, 0, 0];

							// Create jMatrix
							var jmatrix = $.jMatrix({
								id:			'kpi_task_matrix',   
								sort_key:	'indicator_to_mo_id',   
								title_text_top_to_bottom:	opts.title_text_top_to_bottom,
								title_text_90deg:			opts.title_text_90deg,
								cols:		kpi_task_matrix_cols,
								sort_asc_class:         'sort_asc',
								sort_desc_class:        'sort_desc',
								click: function(event) {
									event.stopPropagation();
								}
							});

							// Prepare

							for(var i in strust_item) {
								var itm_item = strust_item[i];
								itm_item.status = task_status[ parseInt(itm_item.fact) ]
									? (matrix.get_simple_behavior(task_status[ parseInt(itm_item.fact) ]['key'])
										? matrix.get_simple_behavior(task_status[ parseInt(itm_item.fact) ]['key'])
										: '<img alt="" src="' + themeLink + 'status' + task_status[ parseInt(itm_item.fact) ]['key'] + '.gif"/>')
									: ''
								;

								// Statistics 
								if(itm_item.indicator_pid == id) { // 1st level
									status_info[itm_item.fact] += parseInt(itm_item.plan);
								}
								
								itm_item.plan = format_number(itm_item.plan / 60, matrix.rounding)
								itm_item.complexity = format_number(itm_item.complexity / 60, matrix.rounding)
								itm_item.weight = format_number(itm_item.weight / 60, matrix.rounding)
							}
							
							// Statistics 
							for (i in task_status) {
								var icon = matrix.get_simple_behavior(task_status[i]['key'])
											? matrix.get_simple_behavior(task_status[i]['key'])
											: '<img alt="" src="' + themeLink + 'status' + task_status[i]['key'] + '.gif" />';
								str.push('<span title="' + get_from_dict('button', 
									'taskStatus' + task_status[i]['key']) + '">' + icon + 
									format_number(status_info[i] / 60, matrix.rounding) + '</span>');
							}

							struct.append('<table class="wMax"><tr>' + arr.join('</tr><tr>') + '</tr></table>');
							struct.append('<div class="status_info">'+str.join(' ')+'</div>');

							// Insert
							jmatrix.paste(strust_item, 'indicator_to_mo_id', true, false);
							struct.append(jmatrix);
						}
					}
					else {
						// Other indicators
						var i = item['struct_data' + id].length, arr = [], j, vals,
							task_status = get('task_status'),
							status_info = [0,0,0,0,0,0,0,0,0],
							str = [], j=0;
							
						while (i--) {
							vals = [];
							arr.push(
								'<td class="tar wMin">' + (item['indicator_behaviour_key' + id] == 'kpi_task'
									?(get('task_status')[ parseInt(item['struct_data' + id][i]['fact']) ]
										? (matrix.get_simple_behavior(get('task_status')[ parseInt(item['struct_data' + id][i]['fact']) ]['key'])
											? matrix.get_simple_behavior(get('task_status')[ parseInt(item['struct_data' + id][i]['fact']) ]['key'])
											: '<img alt="" src="' + themeLink + 'status' + get('task_status')[ parseInt(item['struct_data' + id][i]['fact']) ]['key'] + '.gif"/>')
										:''
									)
									:format_number(item['struct_data' + id][i]['fact'], 2)) +
								'</td><td><b>' + item['struct_data' + id][i]['name'] +
								'</b></td><td class="wMin tar">' +
								(item['indicator_behaviour_key' + id] == 'kpi_task' && !item['struct_data' + id][i]['weight']
									?'?'
									:item['struct_data' + id][i]['weight']
								) +
								(item['struct_data' + id][i]['indicator_measure_name']?', ' + item['struct_data' + id][i]['indicator_measure_name'][0] + '.':'') +
								'</td><td style="width:100px">' + get_from_dict('field', 'from') + ' ' + item['struct_data' + id][i]['live_start'].substr(0, 10) + ' ' + get_from_dict('field', 'to') + ' ' + item['struct_data' + id][i]['live_end'].substr(0, 10)
							);
							if ((item['indicator_behaviour_key' + id] == 'kpi_task') || (item['indicator_behaviour_key' + id] == 'task')) {
								j++;
								status_info[parseInt(item['struct_data' + id][i]['fact'])] = parseFloat(status_info[parseInt(item['struct_data' + id][i]['fact'])]) + parseFloat(item['struct_data' + id][i]['weight']);
							}
						}
						if ( j > 0) {
							for (i in task_status) {
								str.push('<span title="' + get_from_dict('button', 'taskStatus' + task_status[i]['key']) + '">' + 
									(matrix.get_simple_behavior(task_status[i]['key'])
										? matrix.get_simple_behavior(task_status[i]['key'])
										: '<img alt="" src="' + themeLink + 'status' + task_status[i]['key'] + '.gif" />') + (status_info[i].toPrecision(2)?status_info[i].toPrecision(2):0) + '</span>');
							}
						}
						item['struct' + id] = '<table class="wMax"><tr>' + arr.join('</tr><tr>') + '</tr></table>';
						if (j > 0) item['struct' + id] += '<div class="status_info">'+str.join(' ')+'</div>';
					}
				}
				if ('res_img' + id in item) {
					item['res_img' + id] = '<img alt="" src="' + up + 'indicator_interpretation/graphic/?indicator_to_mo_id=' + item['res_img' + id] + '&type=R" />';
				}
				if ('fact_img' + id in item) {
					item['fact_img' + id] = '<img alt="" src="' + up + 'indicator_to_mo/graph/?indicator_to_mo_id=' + item['fact_img' + id] + '&rand=' + Math.random() + '" />';
				}
				var types = {fact: 0, plan: 1};
				for(plan in types) {
					if (plan+'s_rec_data' + id in item) {
						var data = item[plan+'s_rec_data' + id];
						var i = data.length, arr = [], j, k, vals;
						while (i--) {
							arr.push('<b>' + data[i].name + '</b>');
							for (j in data[i].items) {
								var rec_item = data[i].items[j];
								vals = [];
								for (k in rec_item.fields) {
									vals.push(rec_item.fields[k]);
								}
								var value = '<b>' + format_number(rec_item.value, matrix.rounding)+ '</b>';
								if(item['indicator_behaviour_key' + id] == 'kpi_task') {
									if(types[plan]) {
										value = format_number(item['weidht' + id], matrix.rounding);
									}
									else {
										value = format_task_status(rec_item.value);
										if(rec_item.complexity) {
											value += ', ' + format_number(rec_item.complexity / 60, matrix.rounding);
										}
									}
								}
								arr.push([
									'&nbsp;&nbsp;&nbsp;&nbsp;<b>' + rec_item.fact_time.substr(0, 10) + '</b>',
									value,
									vals.join(', ')
								].join(', '))
							}
						}
						item[plan+'s_rec' + id] = arr.join('<br />');
					}
				}

                if ('missed_fact_data' + id in item
                &&  'missed_plan_data' + id in item
                ) {
                    item['missed' + id] = '';
                    if (item['missed_plan_data' + id]) {
                        item['missed' + id] += '<div>' + get_from_dict('field', 'plan3') + ': ' + item['missed_plan_data' + id] + '</div>';
                    }
                    if (item['missed_fact_data' + id]) {
                        item['missed' + id] += '<div>' + get_from_dict('field', 'fact3') + ': ' + item['missed_fact_data' + id] + '</div>';
                    }
                    if (!item['missed' + id]) {
                        item['missed' + id] = '<span class="tick">&nbsp;</span>';
                    }
                }
                /*
                if ('plans_rec' + id in item) {
                    var i = item['plan_data' + id].length, arr = [], j, vals;
                    while (i--) {
                        vals = [];
                        for (j in item['plan_data' + id][i]['fields']) {
                            vals.push(item['plan_data' + id][i]['fields'][j]);
                        }
                        arr.push([
                            '<b>' + item['plan_data' + id][i]['fact_time'].substr(0, 10) + '</b>',
                            '<b>' + item['plan_data' + id][i]['value'] + '</b>',
                            vals.join(', ')
                        ].join(', '));
                        
                    }
                    item['plans_rec_info' + id] = arr.join('<br />');
                }
                */
			}
            else {
                item['weight' + id] = '';
                item['weight_expression' + id] = '';
                item['fact' + id] = '';
                item['fact_expression' + id] = '';
                item['plan' + id] = '';
                item['plan_expression' + id] = '';
                item['res' + id] = '';
                item['res_expression' + id] = '';
                item['fact_data' + id] = '';
                item['plan_data' + id] = '';
                item['struct_data' + id] = '';
                item['res_img' + id] = '';
                item['plans_rec' + id] = '';
                item['facts_rec' + id] = '';
                item['period' + id] = '';
            }
			return item;
		}
	,	format_task_status = function(value) {
			value = parseInt(value);
			var result = '';
			if(task_statuses[value]) {
				result = matrix.get_simple_behavior(task_statuses[value]['key']) 
						? matrix.get_simple_behavior(task_statuses[value]['key'], true) 
						: '<img alt="" src="' + themeLink + 'status' + task_statuses[value]['key'] + '.gif"/>';
			}
			return result;
		}
	,	clear = function() {
			set_id();
			set_name(get_from_dict('string', 'newReportTitle'));
			$(':checkbox', $mo_list).removeAttr('checked');	
			$(':checkbox', $indicator_list).removeAttr('checked');
            $(':checkbox', $field_list).removeAttr('checked');
            $(':checkbox', $field_list).each(function () {
                if (in_array(parseInt(this.value), cheked_params_list)) {
                    $(this).attr('checked', true);
                }
            });
			$(':checkbox', $option_list).removeAttr('checked');	
			display();
			return false;
	}
	,	save = function() {
	    	var mo_all = $('INPUT', $mo_all).attr('checked')?1:0
			,	indicator_list = $('TBODY INPUT:checked', $indicator_list).map(function() {
					return this.value;
				}).get()
			,   indicator_all = $('INPUT', $indicator_all).attr('checked')?1:0
            ,	field_list = $('INPUT:checked', $field_list).map(function() {
					return this.value;
				}).get()
			,	option_list = [];
			$('INPUT:checked', $option_list).each(function() {
				option_list.push({name: this.value, value: this.value});
				//[this.value] = this.value;
			});
			$('select,INPUT[type!="checkbox"]', $option_list).each(function(){
				//option_list[$(this).attr('data-id')] = $(this).val();
				option_list.push({name: $(this).attr('data-id'), value: $(this).val()});
			});
			/*$('INPUT:checked', $option_list).map(function() {
					return this.value;
				}).get();
			option_list = option_list.concat($('INPUT[type="hidden"]', $option_list).map(function() {
					return this.value;
			}).get());*/
			// Mo
			var id;
			var mo_list = [];
			for(id in mo_rows) {
				// Check self
				var self_checked = mo_rows[id].$.find(':checkbox').attr('checked');
				
				// Check childs
				if(mo_childs[id]) {
					var unchecked_childs = false;
					for(var j in mo_childs[id]) {
						var mo_child = mo_childs[id][j];
						if(!mo_child.$.find(':checkbox').attr('checked')) {
							unchecked_childs = true;
						}
					}
					if(!unchecked_childs) {
						// Check self
						var sign = self_checked ? '+' : '-';
						mo_list.push(id + sign);
					}
				}

                if(self_checked) {
					mo_list.push(id);
				}
			}
                
            if (!mo_list.length && !mo_all) {
				c_alert(get_from_dict('string', 'reportMoListEmpty'));
			}
			else if (!indicator_list.length && !indicator_all) {
				c_alert(get_from_dict('string', 'reportIndicatorListEmpty'));
			}
			else if (!field_list.length) {
				c_alert(get_from_dict('string', 'reportFieldListEmpty'));
			}
			else {
				check_sort();
                $.ajax({
					url: up + 'new_report/save'
				,	data: {
						report_id:			report_id
					,	name:				report_name
                    ,   sort_key:           sort_key
                    ,   sort_mode:          sort_mode
					,	'mo_list[]':		mo_list
                    ,   mo_all:             mo_all
                    ,   'indicator_list[]': indicator_list
					,	indicator_all:      indicator_all
					,	'field_list[]':		field_list
					,	'option_list':		$.param(option_list)
					}
				,	success: function(data) {
						set_id(data.report_id);
						new_load_report_list();
					}
				});	
			}
			return false;
		}
	,	export_to_csv = function() {
			show_loading_indicator();
			force_download(table2csv('report'), $report_win.title() + '.csv');
			hide_loading_indicator();
		}
	,	del = function() {
			if (window.confirm(get_from_dict('string', 'questionReportDelete'))) {
				$.ajax({
					url: up + 'new_report/del'
				,   data: {report_id: report_id}
				,	success: function() {
						clear();
						new_load_report_list();
					}
				});
			}
			return false;
		}
	,	close = function() {
			$report_win.close();
		};
	// constructor
    init();
	// public
	return {
		clear:	clear
	,	open:	open
	,	close:	close
	}
};
