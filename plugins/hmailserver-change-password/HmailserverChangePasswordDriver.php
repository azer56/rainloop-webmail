<?php

class HmailserverChangePasswordDriver implements \RainLoop\Providers\ChangePassword\ChangePasswordInterface
{
	/**
	 * @var string
	 */
	private $sLogin = '';

	/**
	 * @var string
	 */
	private $sPassword = '';
	
	/**
	 * @var array
	 */
	private $aDomains = array();

	/**
	 * @var \MailSo\Log\Logger
	 */
	private $oLogger = null;
	
	/**
	 * @param string $sLogin
	 * @param string $sPassword
	 *
	 * @return \CpanleChangePasswordDriver
	 */
	public function SetConfig($sLogin, $sPassword)
	{
		$this->sLogin = $sLogin;
		$this->sPassword = $sPassword;

		return $this;
	}

	/**
	 * @param array $aDomains
	 *
	 * @return \CpanleChangePasswordDriver
	 */
	public function SetAllowedDomains($aDomains)
	{
		if (\is_array($aDomains) && 0 < \count($aDomains))
		{
			$this->aDomains = $aDomains;
		}

		return $this;
	}

	/**
	 * @param \MailSo\Log\Logger $oLogger
	 *
	 * @return \CpanleChangePasswordDriver
	 */
	public function SetLogger($oLogger)
	{
		if ($oLogger instanceof \MailSo\Log\Logger)
		{
			$this->oLogger = $oLogger;
		}

		return $this;
	}

	/**
	 * @param \RainLoop\Account $oAccount
	 *
	 * @return bool
	 */
	public function PasswordChangePossibility($oAccount)
	{
		return $oAccount && $oAccount->Domain() &&
			\in_array(\strtolower($oAccount->Domain()->Name()), $this->aDomains);
	}

	/**
	 * @param \RainLoop\Account $oHmailAccount
	 * @param string $sPrevPassword
	 * @param string $sNewPassword
	 *
	 * @return bool
	 */
	public function ChangePassword(\RainLoop\Account $oHmailAccount, $sPrevPassword, $sNewPassword)
	{
		if ($this->oLogger)
		{
			$this->oLogger->Write('Try to change password for '.$oHmailAccount->Email());
		}

		$bResult = false;

		try
		{
			$oHmailApp = new COM("hMailServer.Application");
			$oHmailApp->Connect();

			if ($oHmailApp->Authenticate($this->sLogin, $this->sPassword))
			{
				$sEmail = $oHmailAccount->Email();
				$sDomain = \MailSo\Base\Utils::GetDomainFromEmail($sEmail);

				$oHmailDomain = $oHmailApp->Domains->ItemByName($sDomain);
				if ($oHmailDomain)
				{
					$oHmailAccount = $oHmailDomain->Accounts->ItemByAddress($sEmail);
					if ($oHmailAccount)
					{
						$oHmailAccount->Password = $sNewPassword;
						$oHmailAccount->Save();

						$bResult = true;
					}
					else
					{
						$this->oLogger->Write('HMAILSERVER: Unknown account ('.$sEmail.')', \MailSo\Log\Enumerations\Type::ERROR);
					}
				}
				else
				{
					$this->oLogger->Write('HMAILSERVER: Unknown domain ('.$sDomain.')', \MailSo\Log\Enumerations\Type::ERROR);
				}
			}
			else
			{
				$this->oLogger->Write('HMAILSERVER: Auth error', \MailSo\Log\Enumerations\Type::ERROR);
			}
		}
		catch (\Exception $oException)
		{
			if ($this->oLogger)
			{
				$this->oLogger->WriteException($oException);
			}
		}

		return $bResult;
	}
}