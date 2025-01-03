<?php

define('API_KEY', ''); # Do not add this file with a populated API_KEY string as changes into your commits, incur in violation.
define('BASE_URL', ''); # modify endpoint as required
define('ENABLE_DEBUG', true);
# change credentials and URL as required. By default these are used.
define('VICIDIAL_USER', 'APIUSER');
define('VICIDIAL_PASS', 'C6Dlr03ceuvJzGg9V1');
define('DIALER_URL', 'https://login.theccdocs.com');
# When data load is distributed across multiple domains, specify the domain here. CCDOCs servers are the default
define('VICIDIAL_DOMAINS', [
	'https://dialer1.theccdocs.com/',
	'https://dialer2.theccdocs.com/',
	'https://login.theccdocs.com/'
]); 
# Timestamp for additional loggers
$localTime = new DateTime();
$date_only = $localTime->format('Y-m-d');
define('TIMESTAMP', $date_only);