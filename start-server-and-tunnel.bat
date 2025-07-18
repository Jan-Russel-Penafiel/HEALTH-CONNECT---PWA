@echo off
setlocal enabledelayedexpansion

:: Define your project names and paths here
set project1=HEALTH CONNECT - Kakadien
set path1=C:\xampp\htdocs\connect

set project2=AID TRACK - Jerome
set path2=C:\xampp\htdocs\jer_new

set project3=IMMUCARE - Michael
set path3=C:\xampp\htdocs\mic_new

set project4=BSCAPS - Muhaimin
set path4=C:\xampp\htdocs\pablo

set project5=KES_SMART - Ligo
set path5=C:\xampp\htdocs\smart



:: Menu
echo.
echo Select a project to serve:
echo 1. %project1%
echo 2. %project2%
echo 3. %project3%
echo 4. %project4%
echo 5. %project5%
set /p choice=Enter number: 

if "%choice%"=="1" set folder=%path1% && set pname=%project1%
if "%choice%"=="2" set folder=%path2% && set pname=%project2%
if "%choice%"=="3" set folder=%path3% && set pname=%project3%
if "%choice%"=="4" set folder=%path4% && set pname=%project4%
if "%choice%"=="5" set folder=%path5% && set pname=%project5%

if not defined folder (
  echo Invalid choice.
  exit /b
)

echo.

:: Open VS Code in the project folder
echo Opening VS Code...
start "" code "!folder!"


:: Start PHP server in a new command window
echo Starting PHP server on port 8080...
start "PHP Server" cmd /k "cd /d "!folder!" && php -S localhost:8080"

:: Wait for server to start
timeout /t 3

echo.
echo Setup complete!
echo - VS Code opened in: !folder!
echo - PHP Server running on: http://localhost:8080
echo.
echo Your application is accessible at: http://localhost:8080
echo.
echo PHP Server is running in a separate window.
echo Closing this window now...
timeout /t 2
exit