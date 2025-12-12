<?php
// Script to perform git operations
echo "Performing git operations...\n";

// Change to the project directory
chdir('c:\xampp\htdocs\Inventory-Flow');

// Add all changes
echo "Adding all changes...\n";
$output = shell_exec('git add . 2>&1');
echo $output;

// Commit changes
echo "Committing changes...\n";
$output = shell_exec('git commit -m "Clean up unnecessary files and improve project structure" 2>&1');
echo $output;

// Push changes
echo "Pushing changes...\n";
$output = shell_exec('git push origin main 2>&1');
echo $output;

echo "Git operations completed.\n";
?>