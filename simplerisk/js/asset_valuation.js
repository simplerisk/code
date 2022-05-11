/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

/******************************
 * FUNCTION: UPDATE MIN VALUE *
 ******************************/
function updateMinValue(id)
{
	// Check for negative values
	//checkNegatives();

	// Get the old and new values of the field that changed
	var min_name = "min_value_" + id;
	var old_value = this.document.getElementsByName(min_name)[0].oldvalue;
	var new_value = this.document.getElementsByName(min_name)[0].value;

	// If the new value is less than the old value
	if (parseInt(new_value) < parseInt(old_value))
	{
		// If the id is not 1
		if (id != 1)
		{
			// We need to decrease the maximum value of the id below it
			var prev_id = id - 1;
			var max_name = "max_value_" + prev_id;
			var value = new_value - 1;
			this.document.getElementsByName(max_name)[0].value = value;

			// Run the update function on the maximum value of the id below
			//updateMaxValue(prev_id);
		}
		// If the id is 1
		else
		{
			// If the new value is negative
			if (parseInt(new_value) < 0)
			{
				// Set the minimum value to 0
				this.document.getElementsByName(min_name)[0].value = 0;
			}
		}
	}
	
        // If the new value is more than the old value
        if (parseInt(new_value) > parseInt(old_value))
        {
		// If the id is not 1
		if (id != 1)
		{
			// We need to increase the maximum value of the id below it
			var prev_id = id - 1;
			var max_name = "max_value_" + prev_id;
			var value = new_value - 1;
			this.document.getElementsByName(max_name)[0].value = value;

			// Run the update function on the maximum value of the id below
			//updateMaxValue(prev_id);
		}

		// Get the max value at the same level
		var max_name = "max_value_" + id;
		var max_value = this.document.getElementsByName(max_name)[0].value;

		// If the max value is less than the new value
		if (max_value < new_value)
		{
			// Set the max value to the new value
			this.document.getElementsByName(max_name)[0].value = new_value;
		}
	}
}

/******************************
 * FUNCTION: UPDATE MAX VALUE *
 ******************************/
function updateMaxValue(id)
{
        // Check for negative values
        //checkNegatives();

        // Get the old and new values of the field that changed
        var max_name = "max_value_" + id;
        var old_value = this.document.getElementsByName(max_name)[0].oldvalue;
        var new_value = this.document.getElementsByName(max_name)[0].value;

        // If the new value is greater than the old value
        if (parseInt(new_value) > parseInt(old_value))
        {
                // If the id is not 10
                if (id != 10)
                {
                        // We need to increase the minimum value of the id above it
                        var next_id = parseInt(id) + 1;
                        var min_name = "min_value_" + next_id;
                        var value = parseInt(new_value) + 1;
                        this.document.getElementsByName(min_name)[0].value = value;

                        // Run the update function on the minimum value of the id above
                        //updateMinValue(next_id);
                }
                // If the id is 10 do nothing
        }

	// If the new value is less than the old value
	if (parseInt(new_value) < parseInt(old_value))
	{
                // If the id is not 10
                if (id != 10)
                {
                        // We need to decrease the minimum value of the id above it
                        var next_id = parseInt(id) + 1;
                        var min_name = "min_value_" + prev_id;
                        var value = parseInt(new_value) + 1;
                        this.document.getElementsByName(min_name)[0].value = value;

			// Run the update function on the minimum value of the id above
			//updateMinValue(next_id);
                }

                // Get the min value at the same level
                var min_name = "min_value_" + id;
                var min_value = this.document.getElementsByName(min_name)[0].value;

                // If the min value is greater than the new value
                if (parseInt(min_value) > parseInt(new_value))
                {
                        // Set the min value to the new value
                        this.document.getElementsByName(min_name)[0].value = new_value;
                }
	}
}

/*****************************
 * FUNCTION: CHECK NEGATIVES *
 *****************************/
function checkNegatives()
{
	// For each level
	for (id = 1; id <= 10; id++)
	{
		// Get the min_value
	        var min_name = "min_value_" + id;
        	var min_value = this.document.getElementsByName(min_name)[0].value;
		
		// If the min_value is negative
		if (parseInt(min_value) < 0)
		{
			// Set it back to its original value
			this.document.getElementsByName(min_name)[0].value = this.document.getElementsByName(min_name)[0].oldvalue;
		}

		// Get the max_value
	        var max_name = "max_value_" + id;
        	var max_value = this.document.getElementsByName(max_name)[0].value;

		// If the max_value is negative
		if (parseInt(max_value) < 0)
		{
			// Set it back to its original value
			this.document.getElementsByName(max_name)[0].value = this.document.getElementsByName(max_name)[0].oldvalue;
		}
	}
}
