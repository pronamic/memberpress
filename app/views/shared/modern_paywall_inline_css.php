<?php

/**
 * Ensure the script is not directly accessed.
 */

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}
?>

<style>
.mepr-paywall-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.7);
  align-items: center;
  justify-content: center;
  z-index: 9999;
  overflow-y: auto;
}

.mepr-paywall-container {
  box-sizing: border-box;
  width: 100%;
  height: auto;
  background-color: #fff;
  border-radius: 2px;
  padding: 20px 15em 3em;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
  position: absolute;
  top: 50%;
  min-height: 50vh;
  transition: all 0.3s ease-out;
}

.mepr-paywall-container.active {
  transition: all 0.3s ease-out;
  top: 20%;
  min-height: 80vh;
}

body:has(.mepr-paywall-overlay) {
  overflow: hidden;
}

@media (max-width: 767px) {
  .mepr-paywall-container {
    padding-left: 40px;
    padding-right: 40px;
  }
}
</style>