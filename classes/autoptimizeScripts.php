<?php

class autoptimizeScripts extends autoptimizeBase
{
	private $scripts = array();
	private $dontmove = array('document.write','google_ad_client','show_ads.js');
	private $domove = array('gaJsHost');
	private $jscode = '';
	private $url = '';
	private $move = array();
	
	//Reads the page and collects script tags
	public function read()
	{
		//Get script files
		if(preg_match_all('#<script.*</script>#Usmi',$this->content,$matches))
		{
			foreach($matches[0] as $tag)
			{
				if(preg_match('#src=("|\')(.*)("|\')#Usmi',$tag,$source))
				{
					//External script
					$url = current(explode('?',$source[2],2));
					$path = $this->getpath($url);
					if($path !==false && preg_match('#\.js$#',$path))
					{
						//Good script
						$this->scripts[] = $path;
					}else{
						//External script (example: google analytics)
						//OR Script is dynamic (.php etc)
						if($this->ismovable($tag))
						{
							$this->move[] = $tag;
						}else{
							//We shouldn't touch this
							$tag = '';
						}
					}
				}else{
					//Inline script
					if($this->ismergeable($tag))
					{
						preg_match('#<script.*>(.*)</script>#Usmi',$tag,$code);
						$code = preg_replace('#.*<!\[CDATA\[(?:\s*\*/)?(.*)(?://|/\*)\s*?\]\]>.*#sm','$1',$code[1]);
						$code = preg_replace('/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/','',$code);
						$this->scripts[] = 'INLINE;'.$code;
					}else{
						//Can we move this?
						if($this->ismovable($tag))
						{
							$this->move[] = $tag;
						}else{
							//We shouldn't touch this
							$tag = '';
						}
					}
				}
				
				//Remove the original script tag
				$this->content = str_replace($tag,'',$this->content);
			}
			
			return true;
		}
	
		//No script files :(
		return false;
	}
	
	//Joins and optimizes JS
	public function minify()
	{
		foreach($this->scripts as $script)
		{
			if(preg_match('#^INLINE;#',$script))
			{
				//Inline script
				$script = preg_replace('#^INLINE;#','',$script);
				$this->jscode .= "\n".$script;
			}else{
				//External script
				if($script !== false && file_exists($script) && is_readable($script))
				{
					$script = file_get_contents($script);
					$this->jscode .= "\n".$script;
				}/*else{
					//Couldn't read JS. Maybe getpath isn't working?
				}*/
			}
		}
		
		//$this->jscode has all the uncompressed code now. 
		if(class_exists('JSMin'))
		{
			$this->jscode = trim(JSMin::minify($this->jscode));
			return true;
		}
		
		return false;
	}
	
	//Caches the JS in uncompressed, deflated and gzipped form.
	public function cache()
	{
		$md5 = md5($this->jscode);
		$cache = new autopimizeCache(WP_PLUGIN_DIR.'/autoptimize/cache/',$md5);
		if(!$cache->check())
		{
			//Cache our code
			$cache->cache($this->jscode,'text/javascript');
		}
		$this->url = WP_PLUGIN_URL.'/autoptimize/cache/'.$cache->getname();
	}
	
	//Returns the content
	public function getcontent()
	{
		$bodyreplacement = implode('',$this->move).'<script type="text/javascript" src="'.$this->url.'"></script></body>';
		$this->content = str_replace('</body>',$bodyreplacement,$this->content);
		return $this->content;
	}
	
	//Checks agains the whitelist
	private function ismergeable($tag)
	{
		foreach($this->domove as $match)
		{
			if(strpos($tag,$match)!==false)
			{
				//Matched something
				return false;
			}
		}
		
		//If we're here it's safe to merge
		return true;
	}
	
	//Checks agains the blacklist
	private function ismovable($tag)
	{
		$allowed = !$this->ismergeable($tag);
		
		if($allowed == true)
		{
			return true;
		}
		
		foreach($this->dontmove as $match)
		{
			if(strpos($tag,$match)!==false)
			{
				//Matched something
				return false;
			}
		}
		
		//If we're here it's safe to move
		return true;
	}
}
