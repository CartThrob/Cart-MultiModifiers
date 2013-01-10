<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * @property CI_Controller $EE
 * @property Cartthrob_cart $cart
 * @property Cartthrob_store $store
 */

/**
 * Cartthrob_modifier_arrays_ext
 * 
 * Splits modifiers that have been added to the cart as an array into multiple single modifiers
 * 
 * NOTE: requires up-to-date versions of Global Item Options, otherwise, GIO will wipe out these modifiers.
 * 
 * docs: 
 * 
 * 
 	do not use row_id in add_to_cart_form, otherwise, there'll be some weirdness if you have items with the same entry id added elsewhere
	{exp:cartthrob:item_options entry_id="123"}
		{if dynamic}
			<label>{option_label}</label>
				{input}
		{if:else}
			{if options_exist}
				<label>{option_label}</label>
				{options} 
					{if option_first_row}
						<select name="item_options[{option_field}][]">{!-- add this as an array: add [] to the item option name --}
					{/if}
					<option {selected} value="{option_value}">
						{option_name}{if option_price_numeric > 0} +{option_price}{/if}
					</option>
					{if option_last_row}
						</select>
					{/if}
				{/options}
			{/if}
		{/if}
	{/exp:cartthrob:item_options}
 *
 * @package default
 * @author Chris Newton
 */
class Cartthrob_modifier_arrays_ext
{
	public $module_name = "CartThrob Modifier Arrays"; 
	public $settings = array();
	public $name = 'CartThrob Modifier Arrays'; 
	public $version = 1;
	public $description = 'Splits modifiers that have been added to the cart as an array into multiple single modifiers. ';  
	public $settings_exist = 'n';
	public $docs_url = 'http://cartthrob.com/';
 	
	
	/**
	* Cartthrob_ext
	*/
	public function __construct($settings='')
	{
		$this->EE =& get_instance();
		$this->EE->load->add_package_path(PATH_THIRD.'cartthrob/'); 
		$this->EE->load->helper(array( 'data_formatting'));
		
	}
	
	public function settings_form()
	{
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module='.$this->module_name);
	}
	
	/**
	 * Activates Extension
	 *
	 * @access public
	 * @param NULL
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function activate_extension()
	{
		$this->EE->db->insert(
			'extensions',
			array(
				'class' => __CLASS__,
				'method' => 'cartthrob_get_all_price_modifiers',
				'hook' 	=> 'cartthrob_get_all_price_modifiers',
				'settings' => '',
				'priority' => 1,
				'version' => $this->version,
				'enabled' => 'y'
			)
		);
		
		$this->EE->db->insert(
			'extensions',
			array(
				'class' => __CLASS__,
				'method' => 'cartthrob_add_to_cart_end',
				'hook' 	=> 'cartthrob_add_to_cart_end',
				'settings' => '',
				'priority' => 10,
				'version' => $this->version,
				'enabled' => 'y'
			)
		);
			
		return TRUE;
	}
	// END
	
	
	// --------------------------------
	//  Update Extension
	// --------------------------------  
	/**
	 * Updates Extension
	 *
	 * @access public
	 * @param string
	 * @return void|BOOLEAN False if the extension is current
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function update_extension($current='')
	{
		if ($current == '' OR $current == $this->version)
		{
			return FALSE;
		}
		
		$this->EE->db->update('extensions', array('version' => $this->version), array('class' => __CLASS__));
		
		return TRUE;
	}
	// END
	
	// --------------------------------
	//  Disable Extension
	// --------------------------------
	/**
	 * Disables Extension
	 * 
	 * Deletes mention of this extension from the exp_extensions database table
	 *
	 * @access public
	 * @param NULL
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function disable_extension()
	{
		$this->EE->db->delete('extensions', array('class' => __CLASS__));
	}
	// END
	
	// --------------------------------
	//  Settings Function
	// --------------------------------
	/**
	 * @access public
	 * @param NULL
	 * @return void
	 * @since 1.0.0
	 * @author Rob Sanchez
	 */
	public function settings()
	{
	}
 
	/**
	 * cartthrob_add_to_cart_end
	 *
	 * does the actual splitting of the cart items into multiple item options
	 * 
	 * @param string $item 
	 * @return void
	 * @author Chris Newton
	 */
	public function cartthrob_add_to_cart_end($item)
	{
		// if for some reason an item was not pased... like add to cart was hit without any cart data being added... or if item is out of stock
		if (!$item)
		{
			return;
		}
		$this->EE->load->helper("array");

		// final base options that will be added to the item... aka standard item options
		$final_options = array(); 
		// these are the array based item options that will be set as meta
		$meta_options = array(); 

		$count = 0; 
 		// standard item options
 		$item_options = $item->item_options(); 

		foreach ($item_options as $key => $value)
		{
			// if it's not an array, then it's a regular option
			if (! is_array($value))
			{
				$final_options[$key] = $value;
			}
			else
			{
				// remove the standard item option, as array content will break stuff
				$item->set_item_options($key, NULL); 
				foreach ($value as $k => $v)
				{
					$count ++; 
 					$meta_options[$key][$count] = $v; 
 				}
			}
		}
		// updating the items options with whatever wasn't an array
		foreach ($final_options as $key => $value)
		{
			$item->set_item_options($key, $value); 
		}

		$opts = array(); 
		// now that we have a list of stacked item options, we're going to go through them, and see if they're connected to options saved in the entry.
		// if they're connected to GIO or standard options, and they're singles, we won't do anything to them. They'll work as normal. IF there is more than one in this entry however
		// we'll split it into two item options that carry their selected option, as well as the other options. 
		foreach ($meta_options as $key => $count)
		{
			// adding defaults so we don't need to worry about missing keys later on. 
			$default = array("option_value" => '1', 'option_name' => '', 'price' => "0", 'option_field' => $key); 
			foreach($count as $k => $v)
			{
				$option_data = array(); 
				$selected_data = array(); 
				$global_option_data = array(); 
				$global_item_options	= (array) $this->EE->cartthrob->cart->meta('global_item_options'); 
				$global_item_options_ext= (array) $this->EE->cartthrob->cart->meta('global_item_options_w_custom_price'); 
				$field_data 			= $this->EE->cartthrob_field_model->get_field_by_name($key); 
				
				$global_item_options = array_merge($global_item_options, $global_item_options_ext); 
				
				if (!empty($global_item_options))
				{
					foreach ($global_item_options as $kk => $vv)
					{
						if (element('short_name', $vv)== $key)
						{
							$d = element('data', $vv); 
	 						if ($d)
							{
								$global_option_data = @ _unserialize($d); 
							}
						}
					}					
				}
				
				// get data from the channel entry
				if ($field_data)
				{
					$field_id = element('field_id', $field_data);
					if ($field_id && $item->product_id())
					{
						$entry_data = $this->EE->cartthrob_entries_model->entry($item->product_id() ); 

					 	if ($tmp = element('field_id_'.$field_id, $entry_data))
						{
							// get the existing options from the field
							$data = @ _unserialize(base64_decode($tmp)); 
							$option_data = $data; 
							foreach ($data as $kk => $vv)
							{
								if (element('option_value', $vv) == $v)
								{
									$selected_data = $vv; 
 									continue; 
								}
								
							}
						}
					}
				}
				// get it from global item options
				elseif ($global_option_data)
				{
					$option_data = $global_option_data; 
	 				
					foreach ($global_option_data as $kk => $vv)
					{
						if (element('option_value', $vv) == $v)
						{
							$selected_data = $vv; 
							continue; 
						}
					}
 				}
				else
				{
					// DYNAMIC
					$price = str_replace("$", "", $v); 
					if (! is_numeric($price))
					{
						$price =0; 
					}
					$option_data = $selected_data = array('option_value' => $v, 'option_name' => ucwords(str_replace("_", " ", $v)), 'price' => $price);  
				}

  				// now we're going to shove the gathered options into a pile. 
				$opts[$key][] = array_merge($default, $selected_data); 
				$full_opts[$key] = $option_data; 
			}
		}

		$final_opts = array(); 
		foreach ($opts as $key => $group)
		{
			$count = 0; 
			
			$totes = count($group); 
			if ($totes > 1)
			{
				foreach ($group as $k => $v)
				{
					$count ++; 
					$key2 = $key ."_". $count; 
					$opts[$key2] = $opts[$key]; 
					$final_opts[$key2] = element($key, $full_opts);
					$opts[$key2][$k]['option_field'] = $key2; 
					$item->set_item_options($key2, $v['option_value']); 
				}
			}
			else
			{
				// there's only one. get the first option
				$opt1 = array_shift($group); 
				
				$item->set_item_options($key, element('option_value', $opt1)); 
				
			}
			unset($opts[$key]); 
			
		}
 		// adding the meta items
		$item->set_meta("item_options", $final_opts); 
		
		$all_meta = $this->EE->cartthrob->cart->meta("all_item_options"); 
		$all_keys = array_keys($final_opts); 
		
		if ($all_meta && is_array($all_meta))
		{
			$all_keys = array_merge($all_keys, $all_meta); 
		}
 		$this->EE->cartthrob->cart->set_meta("all_item_options", $all_keys);
	}

   	public function cartthrob_get_all_price_modifiers($entry_id, $configurations = TRUE, $row_id = FALSE)
	{
 		$last_call = NULL; 
		
		if ($this->EE->extensions->last_call)
		{
			$last_call = $this->EE->extensions->last_call; 
		}

		$meta_options = array(); 
		$price_modifiers = NULL; 

		foreach ($this->EE->cartthrob->cart->items() as $item)
		{
			if ($item->product_id() == $entry_id )
			{
  				// get any meta options
				$meta_options =  $item->meta("item_options"); 
				$price_modifiers = $meta_options; 
			}
		}
		
		if (!empty($last_call) && is_array($last_call))
		{
			$price_modifiers = array_merge($price_modifiers, $last_call);  
		}

		if (is_array($price_modifiers))
		{
	   		return $price_modifiers; 
		}
		return; 
	}
	
	/**
	 * arr
	 *
	 * @param array $array 
	 * @param string $key 
	 * @return string|null
	 * @author Chris Newton
	 * 
	 * Checks an array for a key, returns it if set, or NULL if not set.
	 */
	function arr($array, $key)
	{
		if (isset($array[$key]))
		{
			return $array[$key]; 
		}
		else
		{
			return NULL; 
		}
	}
}
// END CLASS
