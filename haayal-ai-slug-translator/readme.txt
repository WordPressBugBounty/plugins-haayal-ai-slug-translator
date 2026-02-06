=== Ailo - AI Slug Translator ===
Contributors: elchananlevavi
Tags: SEO, slugs, translation, OpenAI, multilingual
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 0.7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically translate non-English slugs into clean, user-friendly English to improve sharing and SEO.

== Description ==

**Why Use This Plugin?**

When sharing links with titles in non-English languages (e.g. Hebrew, Korean, Japanese, Hindi, Arabic, Chinese, or Russian) on platforms like Facebook or WhatsApp, the URLs often turn into a confusing string of codes. This makes your links look unprofessional, reduces click-through rates, and can harm your SEO.

The Automatic Slug Translator fixes this issue by seamlessly translating slugs into concise English. Not only does this make your links visually appealing and user-friendly, but it also enhances your website's search engine performance with clear, descriptive URLs.

**Key Benefits:**
- **Improves Sharing:** Makes links cleaner and more attractive on social platforms.
- **Boosts SEO:** Search engines favor clear, readable URLs.
- **Simplifies Titles:** Long, complex titles are automatically shortened into elegant slugs.

**Example:**
- **Original Title (Hebrew):** איך להשתמש בממיר אוטומטי לסלאג באנגלית
- **Default Slug:** /איך-להשתמש-בממיר-אוטומטי-לסלאג-באנגלית
- **Broken URL:** /%D7%90%D7%99%D7%9A-%D7%9C%D7%94%D7%A9%D7%AA%D7%9E%D7%A9...
- **Clean English Slug:** /how-to-use-automatic-slug-converter

This small adjustment can have a big impact on how your content is shared and discovered.

**Clean English Slugs — Instantly, with AI**

No setup required. This plugin uses AI to automatically translate your post titles and terms into elegant, SEO-friendly English slugs.  
You get **100 translations for free**, and then you can connect your own OpenAI account to keep going.

== Installation ==

1. Install the plugin through the WordPress admin plugins screen or upload the plugin files to `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Configure the plugin in the settings screen under "Slug Translator".

== Usage ==

### Try It Instantly — No OpenAI Account Needed

- Install and activate the plugin — that’s it!
- You can start using the plugin immediately with **100 free translations** included.

### Want More? Connect Your Own API Key

After using your 100 free translations, continue seamlessly by connecting your own [OpenAI](https://openai.com) account:
  1. [Sign up for OpenAI](https://openai.com/signup) and add billing details
  2. Generate an API key from the [API Keys page](https://platform.openai.com/account/api-keys)
  3. Paste the API key into the plugin settings

### Configuration

- In the plugin settings screen, choose which **post types** and **taxonomies** you want to enable translation for.

### How It Works

1. **Automatic Translation**  
   - New posts and taxonomy terms automatically get a clean English slug  
   - Slugs are generated only if you don’t define one manually

2. **Review Translations**  
   - Generated slugs are designed to be short and clear  
   - Double-check that the meaning is preserved, especially for ambiguous titles

== Costs ==

The plugin is completely free to use, and includes 100 slug translations at no cost. After you’ve used the free quota you’ll need a paid OpenAI subscription to continue.

- **Affordable Rates:** For just $1, you can translate between 10,000–20,000 titles, depending on their length.
- **[Check OpenAI Pricing](https://openai.com/pricing):** Ensure your account is funded before use.

**Disclaimer:** While the plugin has been tested to be efficient and cost-effective, users are responsible for monitoring their OpenAI usage and costs. The plugin creator is not liable for unexpected charges due to misuse or errors.

== Third-Party Services ==

This plugin integrates with OpenAI's API to generate text-based responses and suggestions based on user input.
The plugin transmits post/CPT titles, term names, and the requesting server's IP address to OpenAI's servers when a request is made.

When using the free built-in translation quota, your post titles, term names, and domain are sent to the developer’s server to process the translation and track usage. No personal data is collected or stored.

[OpenAI Terms of Service](https://openai.com/terms)  
[OpenAI Privacy Policy](https://openai.com/privacy)

== Frequently Asked Questions ==

= What should I do if slugs are not being translated? =
- Check if you've used up your free translation quota.
- Ensure you have enabled the relevant post types and taxonomies in the plugin settings.
- Verify that your API key is valid and correctly configured.
- Confirm your OpenAI account has an active payment method and sufficient funds.
- Check for potential service disruptions on OpenAI's [status page](https://status.openai.com), as temporary downtime may cause translation issues.

---

= Can I use the plugin without an OpenAI account? =
Yes! Each site gets **100 free translations**. You can start using the plugin right away - no API key or registration required.

Once your free quota is used up, you'll see a notice inviting you to enter your own API key to continue.

---

= How do I know how many free translations I have left? =
Your remaining quota is displayed in the plugin’s settings screen. Once you reach the limit, the plugin will prompt you to connect your own OpenAI account.

---

= What happens when I run out of free translations? =
Slug translation will stop unless you connect your OpenAI API key. You’ll see a message in the settings area with instructions on how to continue using the plugin.

---

= Can I Translate Slugs in Bulk? =
The plugin is designed to translate slugs automatically when new content is created. It doesn’t support bulk translation of existing posts or terms, as changing many URLs at once without setting proper redirects can harm your site’s SEO and break existing links.

---

= What happens if I deactivate or delete the plugin? =
No worries - slugs that were already translated will stay exactly as they are. Your links won’t break, and your content will remain accessible. However, new slugs won’t be translated automatically until you reinstall or reactivate the plugin.

== Screenshots ==

1. The plugin’s settings screen, where you can configure options and (optionally) enter your OpenAI API key.
2. Automatic slug translation in the Gutenberg editor — just type your title and the slug is generated.
3. Automatic slug translation in the Classic Editor — the translated slug appears below the title.

== Changelog ==

= 0.6 =
- Initial release with support for automatic slug translation using OpenAI.
= 0.6.1 =
- Minor bug fixes and new name: HaAyal AI Slug Translator (HaAyal prefix).
= 0.6.2 =
- Minor bug fixes.
= 0.6.3 =
Added automatic API key validation upon saving settings
Displays admin notices for API status: valid, invalid, or insufficient quota
Improved error handling for API communication
Minor UI improvements and clearer helper text
= 0.7 =
Introduced free translation (no OpenAI account required):
- Each domain receives 100 free translations to test the plugin
- Remaining free quota is displayed in the settings screen
- Admin notice appears when free quota is depleted and no API key is provided

Improved architecture:
- Responses now include remaining translation count
- Slug translation method is dynamically selected based on settings and availability
= 0.7.1 =
- Refined usage guide for clarity and accuracy.
= 0.7.2 =
- New name: Ailo - AI Slug Translator.
- Updated class prefix to `Haayal` for consistency and clarity
- Simplified settings page UI for improved usability
- Improved log message when translation quota is exceeded
= 0.7.3 =
- Minor bug fixes.
= 0.7.4 =
- Bug fix: Resolved fatal error that could occur when saving a post without a title.
- Bug fix: Prevented duplicate slug generation caused by both autosave and manual save triggering the translation process.
- Added a welcome admin notice after plugin activation, guiding users to configure which content types to translate.
- Added a friendly prompt inviting users to rate the plugin on the WordPress plugin directory.
- Tested and confirmed compatibility with WordPress 6.9.