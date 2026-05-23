/* Privacy Policy & Terms Modal — Jilz Perfume Bar
   Shows only once per browser. Stored in localStorage as 'jilzPrivacyAccepted'. */

(function () {

    // Check if user already accepted — if yes, do nothing
    if (localStorage.getItem('jilzPrivacyAccepted') === 'true') return;

    // Build modal HTML and inject into <body>
    const modalHTML = `
    <div class="privacyOverlay active" id="privacyOverlay" role="dialog" aria-modal="true" aria-labelledby="privacyModalHeading">

        <div class="privacyModal">

            <!-- Header with logo lockup -->
            <div class="privacyModalHeader">
                <img src="assets/Logo_Tentative.png" alt="Jilz Logo">
                <div class="privacyModalHeaderText">
                    <span class="privacyBrandName">Jilz</span>
                    <span class="privacyBrandSub">perfume bar</span>
                </div>
                <span class="privacyModalTitle" id="privacyModalHeading">Before You Continue</span>
            </div>

            <!-- Gold accent line -->
            <div class="privacyModalAccent"></div>

            <!-- Tab switcher -->
            <div class="privacyTabs" role="tablist">
                <button class="privacyTab active" role="tab" aria-selected="true"  data-tab="privacy"  onclick="switchPrivacyTab('privacy')">Privacy Policy</button>
                <button class="privacyTab"        role="tab" aria-selected="false" data-tab="terms"    onclick="switchPrivacyTab('terms')">Terms &amp; Conditions</button>
            </div>

            <!-- Scrollable content -->
            <div class="privacyContent" id="privacyContent">

                <!-- Privacy Policy panel -->
                <div class="privacyPanel active" id="privacyPanel-privacy">
                    <h2>Privacy Policy</h2>
                    <span class="privacyEffDate">Effective Date: May 18, 2026</span>
                    <p>Welcome to Jilz Perfume Bar. Your privacy is important to us. This Privacy Policy explains how we collect, use, store, and protect your personal information when you use our website and services.</p>

                    <h3>1. Information We Collect</h3>
                    <p>When you create an account or book our services, we may collect:</p>
                    <ul>
                        <li>Full name, email address, and phone number</li>
                        <li>Home address and event location</li>
                        <li>Event date and time</li>
                        <li>Selected package, perfume, bottle, and setup details</li>
                        <li>Payment information (GCash or cash)</li>
                    </ul>

                    <div class="privacyDivider"></div>

                    <h3>2. How We Use Your Information</h3>
                    <p>We use the information collected to create and manage your account, process and confirm bookings, customize your selections, process payments, provide support, and improve our services.</p>

                    <div class="privacyDivider"></div>

                    <h3>3. Payment Policy</h3>
                    <p>A <strong>50% down payment</strong> is required to confirm a booking. The remaining balance must be paid on the event date.</p>

                    <div class="privacyDivider"></div>

                    <h3>4. Refund Policy</h3>
                    <p>Refunds are available only if the booking is canceled at least <strong>one (1) week</strong> before the scheduled event date. Once the cancellation request is approved, the refund will be processed and the amount will be returned to the user within <strong>24 hours</strong>.</p>

                    <div class="privacyDivider"></div>

                    <h3>5. Cancellation Policy</h3>
                    <p>Customers may cancel their bookings provided that cancellation is requested at least <strong>one (1) week</strong> before the scheduled event date.</p>

                    <div class="privacyDivider"></div>

                    <h3>6. Data Security</h3>
                    <p>We implement reasonable administrative and technical safeguards to protect your personal information from unauthorized access, disclosure, alteration, or destruction.</p>

                    <div class="privacyDivider"></div>

                    <h3>7. Cookies &amp; Tracking</h3>
                    <p>Our website does not use cookies or tracking technologies for analytics or advertising purposes.</p>

                    <div class="privacyDivider"></div>

                    <h3>8. Third-Party Services</h3>
                    <p>Our website is hosted by Hostinger. Third-party providers may process your information only as necessary to deliver their services.</p>

                    <div class="privacyDivider"></div>

                    <h3>9. Age Restriction</h3>
                    <p>Our services are intended only for individuals who are <strong>18 years old or older</strong>. By using the website, you confirm that you are at least 18 years of age.</p>

                    <div class="privacyDivider"></div>

                    <h3>10. Contact</h3>
                    <p>For questions about this Privacy Policy, contact us at <strong>jilz@jilz.perfume.shop</strong>.</p>
                </div>

                <!-- Terms & Conditions panel -->
                <div class="privacyPanel" id="privacyPanel-terms">
                    <h2>Terms &amp; Conditions</h2>
                    <span class="privacyEffDate">Effective Date: May 18, 2026</span>
                    <p>By accessing and using the Jilz Perfume Bar Booking Website, you agree to be bound by the following Terms and Conditions.</p>

                    <h3>1. Eligibility</h3>
                    <p>You must be at least <strong>18 years old</strong> to create an account and use our booking services.</p>

                    <div class="privacyDivider"></div>

                    <h3>2. Account Registration</h3>
                    <p>You are required to provide accurate, complete, and current information. You are responsible for the confidentiality of your account credentials and all activities conducted under your account.</p>

                    <div class="privacyDivider"></div>

                    <h3>3. Booking Services</h3>
                    <p>Customers may book perfume bar packages and customize perfume scents, bottle options, and table setup arrangements. All bookings are subject to availability and confirmation by Jilz Perfume Bar.</p>

                    <div class="privacyDivider"></div>

                    <h3>4. Payment Terms</h3>
                    <p>A <strong>50% down payment</strong> is required to secure a booking. The remaining balance must be paid on the event date.</p>

                    <div class="privacyDivider"></div>

                    <h3>5. Cancellation &amp; Refunds</h3>
                    <p>Bookings may be canceled at least <strong>one (1) week</strong> before the event date. Once the cancellation request is approved, the refund will be processed and returned to the user within <strong>24 hours</strong>.</p>

                    <div class="privacyDivider"></div>

                    <h3>6. User Responsibilities</h3>
                    <ul>
                        <li>Provide truthful information</li>
                        <li>Use the website only for lawful purposes</li>
                        <li>Refrain from unauthorized access to the system</li>
                        <li>Respect the intellectual property of Jilz Perfume Bar</li>
                    </ul>

                    <div class="privacyDivider"></div>

                    <h3>7. Intellectual Property</h3>
                    <p>All website content, including text, images, logos, and system features, is the property of Jilz Perfume Bar and may not be copied, reproduced, or distributed without prior written permission.</p>

                    <div class="privacyDivider"></div>

                    <h3>8. Limitation of Liability</h3>
                    <p>Jilz Perfume Bar shall not be liable for any indirect, incidental, or consequential damages arising from the use of the website or services, except where liability cannot be excluded by applicable law.</p>

                    <div class="privacyDivider"></div>

                    <h3>9. Third-Party Hosting</h3>
                    <p>The website is hosted through Hostinger. Availability may be affected by maintenance, outages, or technical issues beyond our control.</p>

                    <div class="privacyDivider"></div>

                    <h3>10. Termination</h3>
                    <p>We reserve the right to suspend or terminate user accounts that violate these Terms and Conditions.</p>

                    <div class="privacyDivider"></div>

                    <h3>11. Contact</h3>
                    <p>For questions regarding these Terms and Conditions, contact us at <strong>jilz@jilz.perfume.shop</strong>.</p>
                </div>

            </div>
            <!-- end .privacyContent -->

            <!-- Footer: checkbox + confirm button -->
            <div class="privacyModalFooter">
                <label class="privacyCheckRow" for="privacyCheckbox">
                    <input
                        type="checkbox"
                        id="privacyCheckbox"
                        class="privacyCheckbox"
                        onchange="togglePrivacyBtn(this)"
                    >
                    <span class="privacyCheckLabel">
                        I have read and agree to the <strong>Privacy Policy</strong> and <strong>Terms &amp; Conditions</strong> of Jilz Perfume Bar.
                    </span>
                </label>

                <button
                    class="privacyConfirmBtn"
                    id="privacyConfirmBtn"
                    onclick="acceptPrivacy()"
                    disabled
                >
                    Continue to Website
                </button>
            </div>

        </div>
        <!-- end .privacyModal -->

    </div>
    <!-- end .privacyOverlay -->
    `;

    // Inject modal right after <body> opens
    document.body.insertAdjacentHTML('afterbegin', modalHTML);

    // Prevent background scrolling while modal is open
    document.body.style.overflow = 'hidden';

})();


/* Switch between Privacy Policy and Terms tabs */
function switchPrivacyTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.privacyTab').forEach(function (btn) {
        btn.classList.toggle('active', btn.dataset.tab === tabName);
        btn.setAttribute('aria-selected', btn.dataset.tab === tabName ? 'true' : 'false');
    });

    // Update panels
    document.querySelectorAll('.privacyPanel').forEach(function (panel) {
        panel.classList.toggle('active', panel.id === 'privacyPanel-' + tabName);
    });

    // Scroll content area back to top on tab switch
    var content = document.getElementById('privacyContent');
    if (content) content.scrollTop = 0;
}


/* Enable / disable the confirm button based on checkbox state */
function togglePrivacyBtn(checkbox) {
    var btn = document.getElementById('privacyConfirmBtn');
    if (!btn) return;

    if (checkbox.checked) {
        btn.classList.add('enabled');
        btn.removeAttribute('disabled');
    } else {
        btn.classList.remove('enabled');
        btn.setAttribute('disabled', '');
    }
}


/* Save acceptance to localStorage and close the modal */
function acceptPrivacy() {
    localStorage.setItem('jilzPrivacyAccepted', 'true');

    var overlay = document.getElementById('privacyOverlay');
    if (overlay) {
        overlay.style.animation = 'privacyFadeOut 0.25s ease forwards';

        // Add fadeout keyframe once
        if (!document.getElementById('privacyFadeOutStyle')) {
            var style = document.createElement('style');
            style.id = 'privacyFadeOutStyle';
            style.textContent = '@keyframes privacyFadeOut { from { opacity:1; } to { opacity:0; } }';
            document.head.appendChild(style);
        }

        setTimeout(function () {
            overlay.remove();
            document.body.style.overflow = '';
        }, 260);
    }
}
