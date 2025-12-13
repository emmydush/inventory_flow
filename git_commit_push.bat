@echo off
cd /d "c:\xampp\htdocs\Inventory-Flow"
echo Adding all changes...
git add .
echo Committing changes...
git commit -m "Clean up unnecessary files and improve project structure"
echo Pushing changes...
git push origin main
echo Done!
pause