<?php

namespace Database\Seeders;

use App\Models\PrivacyPolicy;
use Illuminate\Database\Seeder;

class PrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        PrivacyPolicy::firstOrCreate(
            ['title' => 'Privacy Policy'],
            [
                'content' => $this->getDefaultContent(),
                'is_active' => true,
                'effective_date' => now(),
            ]
        );
    }

    private function getDefaultContent(): string
    {
        return <<<'POLICY'
This Privacy Policy explains what information GIG ("we", "us") collects, why we collect it, how we use and share it, and the rights available to users regarding their personal information. This Policy applies to our website, mobile apps, and related services (collectively "Services").

1. Summary of key points

- Data we collect: account info, contact details, job details (location, employer info, wages), device & usage data, analytics, and optionally payment information.
- Why we collect it: to provide and improve the Services, send reminders/notifications, process payments, comply with legal obligations, and for safety/security.
- User rights: access, correction, deletion, portability, objection/complaint (GDPR); opt-out of sale/sharing (CCPA/CPRA) where applicable.
- Third parties: we share data with service providers, payment processors, analytics vendors, and legal authorities when required.

2. What information we collect

A. Information you provide
- Account registration: name, email, password; phone number is optional if you choose to provide it.
- Profile: display name, photo (optional).
- Job tracking fields: job title/description, employer contact info, job location (address / GPS if enabled), date/time, wages, notes, invoices you upload.
- Communications: support requests, feedback, messages to other users (if applicable).

B. Automatically collected information
- Device identifiers, IP address, device model, operating system, app usage logs, crash reports, analytics events.
- Location data if you enable GPS/location services.

C. Payment & billing information
- Payment tokens, transaction records, billing address (processed by PCI-compliant third-party payment providers).

D. Sensitive data
- We do not request medical or highly sensitive categories. If processing sensitive categories (e.g., wage/financial details considered sensitive by law), we will request explicit consent where required.

3. How we use your information

- Provide, operate, maintain and improve the Services.
- Process payments and invoices.
- Send transactional messages and reminders (with your consent).
- Provide customer support.
- Prevent fraud and abuse; secure our Services.
- Comply with legal obligations.

Legal bases (GDPR/EEA users): contract performance, consent (notifications), legitimate interests (security, analytics).

4. Sharing and disclosure

- Service providers (hosting, analytics, notifications, payment processors).
- Business transfers (mergers, acquisitions).
- Legal requirements (court orders, authorities).
- With your consent (if you share job info externally).

5. Data retention

Account data is typically retained up to 3 years after account deletion. Transaction/billing records are retained up to 7 years as required by law.

6. Data security

We use administrative, technical, and physical safeguards to protect your data. No internet transmission is 100% secure; absolute security cannot be guaranteed.

7. Your rights and choices

- Access & portability of your data.
- Correction of inaccurate data.
- Deletion (with legal exceptions).
- Restriction or objection to processing.
- Opt-out of sale/sharing (California residents).

Contact us at info@gigfmi.com with 'Privacy Request' in the subject. We will verify your identity before fulfilling requests.

8. International transfers

Data may be stored or processed in other countries. Safeguards such as Standard Contractual Clauses apply where required.

9. Children's privacy

We do not knowingly collect data from children under 13. If collected unintentionally, it will be deleted.

10. Third-party links & services

Our Services may link to third-party tools (maps, payments, analytics). Their privacy policies apply separately.

11. Google Play / App Store notices

We comply with platform-specific data-safety requirements. Disclosures must match this Policy.

12. Changes to this Policy

We may update this Policy. If changes are material, we will notify you via email or in-app.

13. Contact & complaints

Data Controller: GIG
Email: info@gigfmi.com

EU residents: contact your local supervisory authority. California residents: may contact the California Attorney General or CPPA.
POLICY;
    }
}
