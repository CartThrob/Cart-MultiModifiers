extension-multi-modifiers
=============================

NOTE: This REQUIRES CartThrob 2.2.6 or greater. It will not work well on lower versions
NOTE: requires up-to-date versions of Global Item Options, otherwise, GIO will wipe out these modifiers.

 Splits modifiers that have been added to the cart as an array into multiple single modifiers

When **adding** items to the cart, do not pass in a row_id, it's not needed, and can screw things up. 

Basic Usage: (this outputs a checkbox list of options)

	{exp:cartthrob:item_options entry_id="123"}
		{if dynamic}
			<label>{option_label}</label>
				{input}
		{if:else}
			{if options_exist}
				<label>{option_label}</label>
				{options} 
					{option_name} {option_price} <input type="checkbox" value="{option_value}" name="item_options[{option_field}][]" />
				{/options}
			{/if}
		{/if}
	{/exp:cartthrob:item_options}

This is a standard EE extension which is installed & configured like other extensions: 
Installation: move file to system > expressionengine > third_party 
Follow additional installation instructions here: 
http://expressionengine.com/user_guide/cp/add-ons/extension_manager.html


You can edit the extensions settings to set the status used for each product channel.




This add-on is provided as-is at no cost with no warranty expressed or implied. Support is not included. 