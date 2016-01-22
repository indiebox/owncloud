<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Security;
use OC\AppFramework\Http\Request;
use OCP\IConfig;

/**
 * Class TrustedDomain
 *
 * @package OC\Security
 */
class TrustedDomainHelper {
	/** @var IConfig */
	private $config;

	/**
	 * @param IConfig $config
	 */
	function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Strips a potential port from a domain (in format domain:port)
	 * @param string $host
	 * @return string $host without appended port
	 */
	private function getDomainWithoutPort($host) {
		$pos = strrpos($host, ':');
		if ($pos !== false) {
			$port = substr($host, $pos + 1);
			if (is_numeric($port)) {
				$host = substr($host, 0, $pos);
			}
		}
		return $host;
	}

	/**
	 * Checks whether a domain is considered as trusted from the list
	 * of trusted domains. If no trusted domains have been configured, returns
	 * true.
	 * This is used to prevent Host Header Poisoning.
	 * @param string $domainWithPort
	 * @return bool true if the given domain is trusted or if no trusted domains
	 * have been configured
	 */
	public function isTrustedDomain($domainWithPort) {
		$domain = $this->getDomainWithoutPort($domainWithPort);

		// Read trusted domains from config
		$trustedList = $this->config->getSystemValue('trusted_domains', []);
		if(!is_array($trustedList)) {
			return false;
		}

		// TODO: Workaround for older instances still with port applied. Remove for ownCloud 9.
		if(in_array($domainWithPort, $trustedList)) {
			return true;
		}

		// Always allow access from localhost
		if (preg_match(Request::REGEX_LOCALHOST, $domain) === 1) {
			return true;
		}

		// Allow access from an explicitly listed domain
		if( in_array($domain, $trustedList)) {
			return true;
		}
 
		// If a value contains a *, apply glob-style matching. Any second * is ignored.
		foreach ($trustedList as $trusted) {
			if($trusted == '*') {
				return true;
			}
			$star = strpos($trusted, '*');
			if($star === false) {
				next;
			}
			if($star === 0) {
				if(strrpos($domain, substr($trusted, 1)) !== FALSE) {
					return true;
				}
            } elseif($star === strlen($trusted)-1) {
				if(strpos($domain, substr($trusted, 0, strlen($trusted)-1 )) !== FALSE) {
					return true;
				}
			} else {
				if(strpos($domain, substr($trusted, 0, $star)) !== false
				&& strrpos($domain, substr($trusted, $star+1 ), -strlen($trusted-$star-1)) !== false )
				{
					return true;
				}
			}
		}
		return false;
	}
}
