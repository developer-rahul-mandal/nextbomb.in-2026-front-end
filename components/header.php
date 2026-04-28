<?php
 const BASE_URL = "http://localhost";
?>

<header class="site-header">
      <a class="brand" href="../" aria-label="NEXTBOMB home">
        <img src="https://nextbomb.in/includes/img/logo.webp" alt="Nextbomb logo" />
        <span class="brand-copy">
          <strong>NEXTBOMB</strong>
          <span>Fast. Safe. Powerful.</span>
        </span>
      </a>

      <input class="nav-toggle-input" type="checkbox" id="nav-toggle" />
      <label class="mobile-nav-toggle" for="nav-toggle" aria-label="Toggle navigation">
        <span class="mobile-nav-label">Menu</span>
        <span class="mobile-nav-bars" aria-hidden="true">
          <span></span>
          <span></span>
          <span></span>
        </span>
      </label>

      <div class="header-panel">
        <nav class="site-nav" aria-label="Primary">
          <a class="nav-link" href="<?= BASE_URL ?>">Home</a>

          <details class="nav-group" name="primary-nav">
            <summary>Tools</summary>
            <div class="nav-menu">
              <a href="<?= BASE_URL ?>/call-bomber">Call Bomber</a>
              <a href="<?= BASE_URL ?>/sms-bomber">SMS Bomber</a>
              <a href="<?= BASE_URL ?>/whatsapp-bomber">WhatsApp Bomber</a>
              <a href="<?= BASE_URL ?>/protect-number">Protect Number</a>
            </div>
          </details>

          <details class="nav-group" name="primary-nav">
            <summary>Info</summary>
            <div class="nav-menu">
              <a href="<?= BASE_URL ?>/about-us">About Us</a>
              <a href="<?= BASE_URL ?>/our-donations">Our Donations</a>
              <a href="<?= BASE_URL ?>/terms-of-service">Terms of Service</a>
              <a href="<?= BASE_URL ?>/privacy-policy">Privacy Policy</a>
              <a href="<?= BASE_URL ?>/contact-us">Contact Us</a>
            </div>
          </details>
        </nav>

        <div class="header-actions">
          <button class="theme-toggle" type="button" aria-label="Toggle dark mode" aria-pressed="false">
            Theme
          </button>
          <button class="install-link site-install-button" type="button" hidden>
            Install
          </button>
        </div>
      </div>
    </header>