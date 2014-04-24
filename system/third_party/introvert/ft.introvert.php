<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Introvert: Fieldtype which outputs reverse related entries to the one being edited
 *
 * @package		ExpressionEngine
 * @subpackage	Fieldtypes
 * @category	Fieldtypes
 * @author    	Iain Urquhart <shout@iain.co.nz>
 * @copyright 	Copyright (c) 2014 Iain Urquhart
 * @license   	All Rights Reserved.
*/

if( !function_exists('ee') )
{
	function ee()
	{
		static $EE;
		if ( ! $EE) $EE = get_instance();
		return $EE;
	}
}

class Introvert_ft extends EE_Fieldtype
	{
		var $info = array(
			'name'		=> 'Introvert',
			'version'	=> '1.2'
		);

		public function display_field($data)
		{
			
			ee()->lang->loadfile('introvert');

			$entry_id = ee()->input->get('entry_id');
			$channels_query = '';

			if(!$entry_id)
				return '<p>'.ee()->lang->line('no_reverse_related_entries').'</p>';
			
			$playa_4_exists = $this->check_playa_table_exists();
			
			ee()->load->helper('date');
			
			// add our assets/js, 
			// we don't need a third party theme folder for just this...
			if (! isset($this->cache['introvert_displayed']))
			{
				ee()->cp->add_js_script(array('plugin' => 'tablesorter')); 
				ee()->javascript->compile();
				
				$js = "<script type='text/javascript'>
						$(document).ready(function() {
							$('.introvert-table').tablesorter({ 
						        sortList: [[1,0]],
						        widgets: ['zebra']
						    }); 
						});
						</script>";
						
				$css  = '<style type="text/css">';
				$css .= '		div.dataTables_wrapper.pageContents.introvert-wrapper {padding: 0 !important; border: none !important;}';
				$css .= '		div.dataTables_wrapper.pageContents.introvert-wrapper a {text-decoration: none;}';
				$css .= '		div.dataTables_wrapper.pageContents.introvert-wrapper a:hover {text-decoration: underline;}';
				$css .= '		div.introvert-wrapper {max-height: 500px; overflow: auto; margin-top: 5px;}';	
				$css .= '</style>';
				
				ee()->cp->add_to_foot($js.$css);
				
				$this->cache['introvert_displayed'] = TRUE;
			}

			$channels = '';

			if($this->settings['channel_preferences'])
			{
				$selected_channels = $this->settings['channel_preferences'];
				$selected_channels = ee()->db->escape_str($selected_channels);
				$channels_query = "AND exp_channel_titles.channel_id IN ($selected_channels)";
			}

			$r  = '';
			$r .= '<div class="dataTables_wrapper pageContents introvert-wrapper"><table class="mainTable introvert-table" border="0" cellspacing="0" cellpadding="0">
						<thead>
						<tr>
							<th style="width: 60%;">Title</th>';
			$r .= '<th>'.ee()->lang->line('channel').'</th>';
			
			$r .= '<th>'.ee()->lang->line('entry_date').'</th>';
			$r .= '<th>'.ee()->lang->line('status').'</th>';
			$r .= '</tr></thead><tbody>';

			$type = 'native_legacy';

			if(APP_VER >= '2.6')
			{
				$type = 'native_evolved';
			}

			$relationships = $this->get_relationships($type, $channels_query, $entry_id);
			
			if($playa_4_exists)
			{
				$playa_relationships = $this->get_relationships('playa_4', $channels_query, $entry_id);
				// combine the arrays to get rid of duplicates
				$relationships = $playa_relationships + $relationships;
			}

			if(count($relationships))
			{	
				// build the table rows
				foreach($relationships as $row)
				{
					$title 			= $row['title'];
					$channel_title 	= $row['channel_title'];
					$channel_id		= $row['channel_id'];
					$entry_date		= mdate("%m/%d/%Y %h:%i %a", $row['entry_date']);
					$status			= ucwords($row['status']);
					$status_highlight	= $row['highlight'];
					$edit_link		= '';
					$can_edit		= FALSE;
					$field_id = $this->settings['field_id'];

					$assigned_channels = ee()->session->userdata['assigned_channels'];

					if(array_key_exists($channel_id, $assigned_channels))
					{
						$edit_link 	= BASE.AMP.'C=content_publish'.AMP.'M=entry_form'.AMP.'channel_id='.$channel_id.AMP.'entry_id='.$row['entry_id'];
						$can_edit = TRUE;
					}

					$r .= "<tr class='introvert-$field_id introvert-row introvert-$status'> \n";
					$r .= ($can_edit) ? "<td><a href='$edit_link'>$title</a></td>" : "<td>$title</td>\n";
					$r .= "<td>$channel_title</td>\n";
					$r .= "<td>$entry_date</td>\n";
					$r .= "<td><span style='color: #$status_highlight;'>$status</span></td>\n";
					$r .= '</tr>';
				}

			}
			// no results
			else
			{
				return '<p>'.ee()->lang->line('no_reverse_related_entries').'</p>';
			}
			
			$r .= '</tbody></table></div>';

			return $r;

		}
		
		function pre_process($data)
		{
			// nada
		}
		
		public function replace_tag($data, $params = FALSE, $tagdata = FALSE)
		{
			// nada	
		}
		
		public function save($data)
		{
			// we're not even saving anything
		}
		
		function post_save($data)
		{
			// nada
		}
		
		public function validate($data)
		{
			return TRUE;
		}
		
		
		public function display_settings($data)
		{
			
			ee()->lang->loadfile('introvert');
			$selected_channels = array();
			
			ee()->load->model('channel_model');
			$channels_query = ee()->channel_model->get_channels();

			foreach ($channels_query->result_array() as $channel)
			{
				$channel_id = $channel['channel_id'];
				$channel_title = $channel['channel_title'];
				$channel_options[$channel_id] = $channel_title;
			}

			if(isset($data['channel_preferences']))
			{
				$selected_channels = explode(',', $data['channel_preferences']) ;
			}
									
			ee()->table->add_row(
				ee()->lang->line('select_channels'),
				form_multiselect('channel_preferences[]', $channel_options, $selected_channels)
			);

 		}
 		
	 	public function save_settings($data)
		{
		
			$channel_preferences = ee()->input->post('channel_preferences');
			
			if(is_array($channel_preferences))
			{
				$channel_preferences = implode(',', $channel_preferences);
			}
			
			return array(
				'channel_preferences'	=> $channel_preferences
			);
		}		

		function install()
		{
			// zip
		}

		function unsinstall()
		{
			// zilch
		}

		// Check playa table exists, 
		// playa 4 stores outside of EE relationship table
		// returns TRUE if playa_relationships table
		private function check_playa_table_exists()
		{
			if ( ! isset(ee()->session->cache['introvert']['playa_exists']))
			{
				if (! ee()->db->table_exists('playa_relationships'))
					return FALSE;
	
				ee()->session->cache['introvert']['playa_exists'] = 1;
			}
			return TRUE;
		}

		// returns an array of relationship entries
		// native or playa_4 supported
		private function get_relationships($type = 'native', $channels = 1, $entry_id = 1)
		{
			switch($type)
			{
				case "native_evolved":
					$rel_table 		= 'exp_relationships';
					$rel_parent_col = 'parent_id';
					$rel_child_col 	= 'child_id';
				break;
				case "playa_4":
					$rel_table 		= 'exp_playa_relationships';
					$rel_parent_col = 'parent_entry_id';
					$rel_child_col 	= 'child_entry_id';
				break;
				default:
				$rel_table 		= 'exp_relationships';
				$rel_parent_col = 'rel_parent_id';
				$rel_child_col 	= 'rel_child_id';	
			}	

			$sql = "SELECT DISTINCT 
					$rel_table.$rel_parent_col as parent_id,
					$rel_table.$rel_child_col as child_id,
					exp_channel_titles.channel_id,
					exp_channel_titles.entry_id,
					exp_channel_titles.status,
					exp_channel_titles.entry_date,
					exp_channels.channel_title,
					exp_statuses.status,
					exp_statuses.highlight,
					exp_channel_titles.title
					
					FROM $rel_table
						
						LEFT JOIN exp_channel_titles
						ON ($rel_table.$rel_parent_col=exp_channel_titles.entry_id)
						
						LEFT JOIN exp_channel_data
						ON ($rel_table.$rel_parent_col=exp_channel_data.entry_id)
						
						LEFT JOIN exp_channels
						ON (exp_channels.channel_id=exp_channel_titles.channel_id)
						
						LEFT JOIN exp_statuses
						ON (exp_channel_titles.status=exp_statuses.status)
					
					WHERE $rel_table.$rel_child_col = '$entry_id'
					
					$channels
					
					ORDER BY exp_channels.channel_title, exp_channel_titles.title";
					
				$query = ee()->db->query($sql);
				
				return ($query->num_rows() > 0) ? $query->result_array() : array();
		
		}
		
	}
	//END CLASS
	
/* End of file ft.introvert.php */