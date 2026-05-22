<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'dashboard';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Auth
$route['login']           = 'auth/login';
$route['logout']          = 'auth/logout';
$route['register']        = 'auth/register';
$route['forgot-password'] = 'auth/forgotPassword';
$route['reset-password/(:any)'] = 'auth/resetPassword/$1';

// Projects
$route['projects']                        = 'projects/index';
$route['projects/create']                 = 'projects/create';
$route['projects/(:num)']                 = 'projects/view/$1';
$route['projects/(:num)/edit']            = 'projects/edit/$1';
$route['projects/(:num)/archive']         = 'projects/archive/$1';

// Divisions (nested under projects)
$route['projects/(:num)/divisions/create'] = 'divisions/create/$1';
$route['divisions/(:num)/delete']          = 'divisions/deleteDivision/$1';

// Submittals (nested under divisions)
$route['divisions/(:num)/submittals/create'] = 'submittals/create/$1';
$route['submittals/(:num)']                  = 'submittals/view/$1';
$route['submittals/(:num)/upload']           = 'submittals/upload/$1';

// Extractions
$route['extractions/(:num)/rerun']           = 'submittals/rerun/$1';

// Compliance matrix & review queue (Phase 4)
$route['submittals/(:num)/compliance']       = 'submittals/compliance/$1';
$route['submittals/(:num)/review']           = 'submittals/review/$1';
$route['submittals/(:num)/decide']           = 'submittals/decide/$1';

// Admin
$route['admin/extractions']                  = 'admin/extractions';
