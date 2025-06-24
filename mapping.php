<?php
/**
 * CSV Mapping Page
 *
 * Handles the mapping of CSV columns to required fields.
 */
session_start();

// Redirect if no CSV file is uploaded
if (!isset($_SESSION['csv_file']) || !isset($_SESSION['csv_headers'])) {
    header('Location: upload.php');
    exit();
}

$csv_headers = $_SESSION['csv_headers'];
// You can add more required fields to this array as needed
$required_fields = ['590']; 

// Add optional fields if needed
$optional_fields = []; // Example: 'name', 'email', etc.

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mapping = $_POST['mapping'];
    $_SESSION['mapping'] = $mapping; // Store mapping in session
    
    // Redirect to preview page
    header('Location: preview.php');
    exit();
}

// Get first row data for 590 if available
$row_590_data = [];
if (isset($_SESSION['csv_data']) && !empty($_SESSION['csv_data'])) {
    foreach ($_SESSION['csv_data'] as $row) {
        if (isset($row[0]) && $row[0] == '590') {
            $row_590_data = $row;
            break;
        }
    }
}

// Function to read CSV and return preview data.
function get_csv_preview( $file_path, $max_rows = 5 ) {
	$preview_data = array();
	$header = array();
	
	if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {
		// Get header row.
		if ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) {
			$header = $data;
		}
		
		// Get preview rows.
		$row_count = 0;
		while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false && $row_count < $max_rows ) {
			$preview_data[] = $data;
			$row_count++;
		}
		
		fclose( $handle );
	}
	
	return array(
		'header' => $header,
		'rows' => $preview_data,
	);
}

// Get CSV preview data.
$preview = get_csv_preview( $_SESSION['csv_file'] );
?>

<!DOCTYPE html>
<html>
<head>
    <title>CSV Mapping</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .mapping-form { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .field-map { margin: 15px 0; position: relative; }
        label { display: inline-block; width: 120px; font-weight: bold; }
        select { width: 250px; padding: 5px; }
        input[type="text"] { width: 200px; padding: 5px; margin-right: 5px; }
        .submit-btn { margin-top: 20px; padding: 8px 15px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .submit-btn:hover { background-color: #45a049; }
        .header { text-align: center; margin-bottom: 20px; }
        .instructions { margin-bottom: 20px; color: #555; }
        .required { color: red; margin-left: 5px; }
        .add-field-btn, .remove-field-btn, .add-all-btn { 
            padding: 2px 8px; 
            margin-left: 5px; 
            border-radius: 3px; 
            cursor: pointer; 
            font-weight: bold;
            border: 1px solid #ccc;
        }
        .add-field-btn, .add-all-btn { 
            background-color: #4CAF50; 
            color: white; 
        }
        .add-all-btn {
            padding: 5px 10px;
            margin-left: 15px;
        }
        .remove-field-btn { 
            background-color: #f44336; 
            color: white; 
        }
        .dynamic-fields-container { 
            border-top: 1px dashed #ccc; 
            margin-top: 20px; 
            padding-top: 15px; 
        }
        .field-name-input {
            margin-right: 10px;
        }
        .field-value {
            display: inline-block;
            width: 200px;
            padding: 5px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-left: 10px;
            font-family: monospace;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .field-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .preview-container {
            margin-bottom: 30px;
            overflow-x: auto;
        }
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .preview-table th, .preview-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .preview-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .preview-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="mapping-form">
        <div class="header">
            <h2>Map CSV Columns</h2>
        </div>
        
        <div class="instructions">
            <p>Please map each required field to the corresponding column in your CSV file. You can also add custom fields below.</p>
        </div>
        
        <!-- CSV Preview Section -->
        <div class="preview-container">
            <h2>CSV Preview</h2>
            <?php if ( ! empty( $preview['header'] ) ) : ?>
                <table class="preview-table">
                    <thead>
                        <tr>
                            <?php foreach ( $preview['header'] as $column ) : ?>
                                <th><?php echo htmlspecialchars($column, ENT_QUOTES, 'UTF-8'); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $preview['rows'] as $row ) : ?>
                            <tr>
                                <?php foreach ( $row as $cell ) : ?>
                                    <td><?php echo htmlspecialchars($cell, ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No CSV data found or file is empty.</p>
            <?php endif; ?>
        </div>
        
        <form method="POST" id="mappingForm">
            <!-- Required fields -->
            <?php foreach ($required_fields as $field): ?>
            <div class="field-map">
                <label><?php echo ucfirst($field); ?>:</label>
                <select name="mapping[<?php echo htmlspecialchars($field); ?>]" required>
                    <option value="">Select CSV Column</option>
                    <?php foreach ($csv_headers as $index => $header): ?>
                    <option value="<?php echo $index; ?>"><?php echo htmlspecialchars($header); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="required">*</span>
            </div>
            <?php endforeach; ?>
            
            <!-- Optional predefined fields -->
            <?php foreach ($optional_fields as $field): ?>
            <div class="field-map">
                <label><?php echo ucfirst($field); ?>:</label>
                <select name="mapping[<?php echo htmlspecialchars($field); ?>]">
                    <option value="">Select CSV Column (Optional)</option>
                    <?php foreach ($csv_headers as $index => $header): ?>
                    <option value="<?php echo $index; ?>"><?php echo htmlspecialchars($header); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
            
            <!-- Dynamic fields section -->
            <div class="dynamic-fields-container">
                <div class="field-header">
                    <h3>Custom Fields</h3>
                    <div>
                        <button type="button" class="add-field-btn" id="addFieldBtn">Add Field</button>
                        <button type="button" class="add-all-btn" id="addAllFieldsBtn">Add All Remaining Fields</button>
                    </div>
                </div>
                <div id="dynamicFields"></div>
            </div>
            
            <input type="submit" value="Process CSV" class="submit-btn">
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addFieldBtn = document.getElementById('addFieldBtn');
            const addAllFieldsBtn = document.getElementById('addAllFieldsBtn');
            const dynamicFields = document.getElementById('dynamicFields');
            let fieldCounter = 0;
            
            // Store CSV headers and 590 row data
            const csvHeaders = <?php echo json_encode($csv_headers); ?>;
            const row590Data = <?php echo json_encode($row_590_data); ?>;
            
            // Track which headers have been used
            const usedHeaders = new Set();
            
            // Mark required fields as used
            <?php foreach ($required_fields as $field): ?>
                document.querySelector('select[name="mapping[<?php echo htmlspecialchars($field); ?>]"]').addEventListener('change', function() {
                    updateUsedHeaders();
                });
            <?php endforeach; ?>
            
            <?php foreach ($optional_fields as $field): ?>
                document.querySelector('select[name="mapping[<?php echo htmlspecialchars($field); ?>]"]').addEventListener('change', function() {
                    updateUsedHeaders();
                });
            <?php endforeach; ?>
            
            // Function to update which headers are used
            function updateUsedHeaders() {
                usedHeaders.clear();
                
                // Get all selects in the form
                const selects = document.querySelectorAll('select[name^="mapping"], select[name^="custom_mapping"]');
                selects.forEach(select => {
                    if (select.value) {
                        usedHeaders.add(parseInt(select.value));
                    }
                });
            }
            
            // Function to add a new field
            function addField(headerIndex) {
                fieldCounter++;
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'field-map';
                fieldDiv.id = `field-${fieldCounter}`;
                
                // Create field name input (pre-populated with CSV header)
                const fieldNameInput = document.createElement('input');
                fieldNameInput.type = 'text';
                fieldNameInput.name = `custom_field_names[${fieldCounter}]`;
                fieldNameInput.className = 'field-name-input';
                fieldNameInput.required = true;
                
                // If a specific header index was provided, use it
                if (headerIndex !== undefined && csvHeaders[headerIndex]) {
                    fieldNameInput.value = csvHeaders[headerIndex];
                    fieldNameInput.readOnly = true; // Make it read-only since it's pre-populated
                }
                
                // Create dropdown for CSV column selection
                const selectEl = document.createElement('select');
                selectEl.name = `custom_mapping[${fieldCounter}]`;
                selectEl.required = true;
                
                // Add default option
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Select CSV Column';
                selectEl.appendChild(defaultOption);
                
                // Add options from CSV headers
                for (let index in csvHeaders) {
                    const option = document.createElement('option');
                    option.value = index;
                    option.textContent = csvHeaders[index];
                    
                    // If a specific header index was provided, select it
                    if (headerIndex !== undefined && index == headerIndex) {
                        option.selected = true;
                    }
                    
                    selectEl.appendChild(option);
                }
                
                // Create value display element (showing 590 row value if available)
                let valueDisplay = '';
                if (headerIndex !== undefined && row590Data[headerIndex]) {
                    valueDisplay = document.createElement('span');
                    valueDisplay.className = 'field-value';
                    valueDisplay.textContent = row590Data[headerIndex];
                    valueDisplay.title = row590Data[headerIndex]; // For tooltip on hover
                }
                
                // Create remove button
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'remove-field-btn';
                removeBtn.textContent = '-';
                removeBtn.onclick = function() {
                    dynamicFields.removeChild(fieldDiv);
                    updateUsedHeaders();
                };
                
                // Append all elements to the field div
                fieldDiv.appendChild(fieldNameInput);
                fieldDiv.appendChild(selectEl);
                if (valueDisplay) {
                    fieldDiv.appendChild(valueDisplay);
                }
                fieldDiv.appendChild(removeBtn);
                
                // Add the new field to the container
                dynamicFields.appendChild(fieldDiv);
                
                // Update used headers
                selectEl.addEventListener('change', updateUsedHeaders);
                if (headerIndex !== undefined) {
                    updateUsedHeaders();
                }
                
                return fieldDiv;
            }
            
            // Add a single field
            addFieldBtn.addEventListener('click', function() {
                addField();
            });
            
            // Add all remaining fields
            addAllFieldsBtn.addEventListener('click', function() {
                updateUsedHeaders();
                
                // Add fields for all headers that aren't already used
                for (let index in csvHeaders) {
                    if (!usedHeaders.has(parseInt(index))) {
                        addField(index);
                    }
                }
            });
            
            // Process form submission to include dynamic fields
            document.getElementById('mappingForm').addEventListener('submit', function(e) {
                const customFieldNames = document.querySelectorAll('[name^="custom_field_names"]');
                const customMappings = document.querySelectorAll('[name^="custom_mapping"]');
                
                // Create hidden inputs for each custom field to be properly included in the mapping
                for (let i = 0; i < customFieldNames.length; i++) {
                    if (customFieldNames[i].value && customMappings[i].value) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = `mapping[${customFieldNames[i].value}]`;
                        hiddenInput.value = customMappings[i].value;
                        this.appendChild(hiddenInput);
                    }
                }
            });
        });
    </script>
</body>
</html> 