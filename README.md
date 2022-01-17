<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Mailchimp Transactional icon"></p>

<h1 align="center">Mailchimp Transactional for Craft CMS</h1>

This plugin provides a [Mailchimp Transactional](https://mailchimp.com/features/transactional-email/) integration for [Craft CMS](https://craftcms.com/).

## Requirements

This plugin requires Craft CMS 3.7.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Mailchimp Transactional”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require perfectwebteam/craft-mailchimp-transactional

# tell Craft to install the plugin
./craft install/plugin craft-mailchimp-transactional
```

## Setup

Once Mailchimp Transactional is installed:

1. Go to **Settings** → **Email**.
2. Make sure that the **System Email Address** is set to an email for which the domain is a verified [Sending Domain](https://mandrillapp.com/settings/sending-domains). 
3. Change the **Transport Type** setting to **Mailchimp Transactional**.
4. Enter your **API Key** from the Mailchimp Transactional [Settings](https://mandrillapp.com/settings) page.
5. Optionally set the **Subaccount** from the Mailchimp Transactional [Subaccounts](https://mandrillapp.com/subaccounts) page.
6. Optionally set the **Template Slug** from the Mailchimp Transactional [Templates](https://mandrillapp.com/templates) page.
7. Click **Save**.

> **Tip:** The API Key, Subaccount and Template Slug settings can be set using environment variables. See [Environmental Configuration](https://craftcms.com/docs/3.x/config/#environmental-configuration) in the Craft docs to learn more about that.
 
Brought to you by [Perfect Web Team](https://perfectwebteam.com)