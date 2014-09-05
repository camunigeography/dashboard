<?php

# Application aggregator dashboard
require_once ('frontControllerApplication.php');
class dashboard extends frontControllerApplication
{
	# Function to assign defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			
			# Application internals
			'useDatabase'		=> false,
			'disableTabs'		=> true,
			'useFeedback'		=> false,
			'authentication'	=> true,
			
			# Settings
			'applicationName'	=> 'Dashboard',
			'services'			=> NULL,		// Must define array of services as array ('Name' => '/path/to/application/', 'Name 2' => 'http://www.example.com/path/to/application/', ...)
			'apiUsername'		=> NULL,
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign supported actions
	public function actions ()
	{
		# Define available tasks
		$actions = array (
			
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Additional constructor processing
	public function main ()
	{
		# Start an registry of services
		$this->services = array ();
		
		# Add each service
		foreach ($this->settings['services'] as $name => $path) {
			
			# Determine the baseUrl
			$baseUrl = preg_replace ("|^(https?://{$_SERVER['SERVER_NAME']})|", '', $path);
			$baseUrl = ((substr ($baseUrl, -1) == '/') ? substr ($baseUrl, 0, -1) : $baseUrl);
			
			# Determine the full API URL
			$apiUrl = $path;
			if (!preg_match ('|/$|', $apiUrl)) {
				$apiUrl .= '/';
			}
			if (preg_match ('|^/|', $apiUrl)) {
				$apiUrl = $_SERVER['_SITE_URL'] . $apiUrl;
			}
			$apiUrl = preg_replace ('|^(https?://)|', '\1' . $this->settings['apiUsername'] . ':@', $apiUrl);
			$apiUrl .= 'api/';
			$apiUrl .= 'dashboard/';
			$apiUrl .= $this->user;
			
			# Determine if the service is an HTTPS service
			$isHttps = preg_match ('|^https://|', $apiUrl);
			
			# Add the service to the registry
			$this->services[$name] = array (
				'name' => $name,
				'nameHtml' => htmlspecialchars ($name),
				'path' => $path,
				'baseUrl' => $baseUrl,
				'isHttps' => $isHttps,
				'apiUrl' => $apiUrl,
				'html' => false,
			);
		}
		
		// application::dumpData ($this->services);
	}
	
	
	# Home page
	public function home ()
	{
		# Create a stream context for the API retrievals
		$streamContextOptions = array ('http' => array ('timeout' => 2, ), 'https' => array ('timeout' => 2, ));
		$streamContext = stream_context_create ($streamContextOptions);
		
		# Get information from each service and compile its HTML
		foreach ($this->services as $serviceId => $service) {
			
			# Start the HTML for this service
			$html = '';
			
			# Add heading
			//$html .= "\n\n\n<h2><a href=\"{$service['path']}\">{$service['nameHtml']}</a></h2>";
			$this->services[$serviceId]['titleHtml']  = "\n\n\n<h2>{$service['nameHtml']}</h2>";
			
			// $html .= $service['apiUrl'];
			
			# Get the data from this service's API endpoint
			if (!$json = file_get_contents ($service['apiUrl'], false, $streamContext)) {
				$html .= "\n<p><em>It was not possible to fetch information from this service (does not exist).</em></p>";
				$this->services[$serviceId]['html'] = $html;
				continue;
			}
			
			# Decode the data
			if (!$data = json_decode ($json, true)) {
				$html .= "\n<p><em>It was not possible to fetch information from this service (not JSON).</em></p>";
				$this->services[$serviceId]['html'] = $html;
				continue;
			}
			
			# Ensure the service is enabled
			if (!isSet ($data['enabled'])) {continue;}
			
			# Ensure the service is authorised for the user
			if (isSet ($data['authorised']) && !$data['authorised']) {continue;}
			
			# Add description, if present, under the title
			if (isSet ($data['descriptionHtml'])) {
				$this->services[$serviceId]['titleHtml'] .= "\n" . $data['descriptionHtml'];
			}
			
			# Check for error
			if (isSet ($data['error'])) {
				$html .= "\n<p><em>It was not possible to fetch information from this service (error occurred).</em></p>";
				$this->services[$serviceId]['html'] = $html;
				continue;
			}
			
			# Add general links
			$links = array ();
			$links["{$service['baseUrl']}/"] = '{icon:cog} ' . $service['name'] . ' dashboard';
			if (isSet ($data['links'])) {
				$links = array_merge ($links, $data['links']);	// array_merge allows the service to override the default home link
			}
			$linksHtml = array ();
			foreach ($links as $url => $text) {
				$text = htmlspecialchars ($text);
				$text = preg_replace ('/{icon:([^}]+)}/', '<img src="/images/icons/\1.png" alt="*" class="icon" />', $text);
				$linksHtml[] = "<a class=\"actions\" href=\"{$url}\">{$text}</a>";
			}
			$linksHtml = "\n<p class=\"mainlinks\">" . implode (' &nbsp; ', $linksHtml) . '</p>';
			
			# Rewrite http links as https if the dashboard is running on http
			$contentHtml = '';
			if (isSet ($data['html'])) {
				if (($_SERVER['_SERVER_PROTOCOL_TYPE'] != 'https') && $service['isHttps']) {
					$data['html'] = preg_replace ("~(\\s)(action|href|src)=\"{$service['baseUrl']}/~", "\\1\\2=\"https://{$_SERVER['SERVER_NAME']}{$service['baseUrl']}/", $data['html']);
				}
				$contentHtml = $data['html'];
			}
			
			# Compile the HTML
			$html .= "\n";
			$html .= "\n<div class=\"graybox\">";
			$html .= $linksHtml;
			$html .= $contentHtml;
			$html .= "\n</div>";
			
			# Register the HTML
			$this->services[$serviceId]['html'] = $html;
			
			$table[$titleHtml] = $html;
		}
		
		# Compile the HTML
		$table = array ();
		foreach ($this->services as $serviceId => $attributes) {
			$key = $attributes['titleHtml'];
			$value = $attributes['html'];
			$table[$key] = $value;
		}
		
		$html = application::htmlTableKeyed ($table, array (), true, 'dashboard lines', $allowHtml = true, $showColons = false);
		
		/*
		# Compile the HTML
		$html = '';
		foreach ($this->services as $serviceId => $attributes) {
			$html .= $attributes['html'];
		}
		*/
		
		# Echo the HTML
		echo $html;
	}
}


?>