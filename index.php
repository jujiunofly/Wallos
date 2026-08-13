<?php

require_once 'includes/header.php';
require_once 'includes/getdbkeys.php';
require_once 'includes/logo_theme_variant.php';

function formatPrice($price, $currencyCode, $currencies)
{
    $formattedPrice = CurrencyFormatter::format($price, $currencyCode);
    if (strstr($formattedPrice, $currencyCode)) {
        $symbol = $currencyCode;

        foreach ($currencies as $currency) {

            if ($currency['code'] === $currencyCode) {
                if ($currency['symbol'] != "") {
                    $symbol = $currency['symbol'];
                }
                break;
            }
        }
        $formattedPrice = str_replace($currencyCode, $symbol, $formattedPrice);
    }

    return $formattedPrice;
}

function formatDate($date, $lang = 'en')
{
    $currentYear = date('Y');
    $dateYear = date('Y', strtotime($date));

    // Determine the date format based on whether the year matches the current year
    $dateFormat = ($currentYear == $dateYear) ? 'MMM d' : 'MMM yyyy';

    // Try to create an IntlDateFormatter; if it fails, fallback to 'en'
    try {
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );

        if (!$formatter) {
            throw new Exception('Failed to create IntlDateFormatter with language: ' . $lang);
        }
    } catch (Throwable $e) {
        $lang = 'en'; // Fallback to English on error
        $formatter = new IntlDateFormatter(
            $lang,
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE,
            null,
            null,
            $dateFormat
        );
    }

    // Format the date
    $formattedDate = $formatter->format(new DateTime($date));

    return $formattedDate;
}

// Get the first name of the user
$stmt = $db->prepare("SELECT username, firstname FROM user WHERE id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);
$first_name = $user['firstname'] ?? $user['username'] ?? '';

// Fetch the next 3 enabled subscriptions up for payment
$stmt = $db->prepare("SELECT id, logo, logo_text_color, logo_variant, name, price, currency_id, next_payment, inactive FROM subscriptions WHERE user_id = :userId AND next_payment >= date('now') AND inactive = 0 AND cycle != 5 ORDER BY next_payment ASC LIMIT 3");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$upcomingSubscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $upcomingSubscriptions[] = $row;
}

// Fetch enabled subscriptions with manual renewal that are overdue
$stmt = $db->prepare("SELECT id, logo, logo_text_color, logo_variant, name, price, currency_id, next_payment, inactive, auto_renew FROM subscriptions WHERE user_id = :userId AND next_payment < date('now') AND auto_renew = 0 AND inactive = 0 AND cycle != 5 ORDER BY next_payment ASC");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$overdueSubscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $overdueSubscriptions[] = $row;
}
$hasOverdueSubscriptions = !empty($overdueSubscriptions);

require_once 'includes/stats_calculations.php';

// Get AI Recommendations for user
$stmt = $db->prepare("SELECT * FROM ai_recommendations WHERE user_id = :userId");
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$aiRecommendations = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $aiRecommendations[] = $row;
}

?>

<section class="contain dashboard">
    <?php
        if ($isAdmin && $settings['update_notification']) {
            if (!is_null($settings['latest_version'])) {
                $latestVersion = $settings['latest_version'];
                if (version_compare($version, $latestVersion) == -1) {
                    ?>
                    <div class="update-banner">
                    <?= translate('new_version_available', $i18n) ?>:
                        <span><a href="https://github.com/ellite/Wallos/releases/tag/<?= htmlspecialchars($latestVersion) ?>"
                        target="_blank" rel="noreferer">
                        <?= htmlspecialchars($latestVersion) ?>
                        </a></span>
                    </div>
                    <?php
                }
            }
        }
        if ($demoMode) {
            ?>
            <div class="demo-banner">
            Running in <b>Demo Mode</b>, certain actions and settings are disabled.<br>
            The database will be reset every 120 minutes.
            </div>
            <?php
        }
    ?>
    <?php
    $heroNextPayment = !empty($upcomingSubscriptions)
        ? formatDate($upcomingSubscriptions[0]['next_payment'], $lang)
        : translate('none', $i18n);
    $heroNextName = !empty($upcomingSubscriptions) ? $upcomingSubscriptions[0]['name'] : '';
    $heroCurrencyCode = $currencies[$userData['main_currency']]['code'];
    ?>
    <div class="dashboard-hero">
        <div>
            <p class="dashboard-kicker"><?= translate('dashboard', $i18n) ?></p>
            <h1><?= translate('hello', $i18n) ?> <?= htmlspecialchars($first_name) ?></h1>
        </div>
        <div class="dashboard-hero-stats">
            <?php if (isset($totalCostPerMonth)) { ?>
                <div class="dashboard-hero-stat">
                    <span><?= translate('monthly_cost', $i18n) ?></span>
                    <strong><?= CurrencyFormatter::format($totalCostPerMonth, $heroCurrencyCode) ?></strong>
                </div>
            <?php } ?>
            <?php if (isset($activeSubscriptions)) { ?>
                <div class="dashboard-hero-stat">
                    <span><?= translate('active_subscriptions', $i18n) ?></span>
                    <strong><?= (int) $activeSubscriptions ?></strong>
                </div>
            <?php } ?>
            <div class="dashboard-hero-stat">
                <span><?= translate('next_payment', $i18n) ?></span>
                <strong title="<?= htmlspecialchars($heroNextName) ?>"><?= htmlspecialchars($heroNextPayment) ?></strong>
            </div>
        </div>
    </div>

    <?php
    // If there are overdue subscriptions, display them
    if ($hasOverdueSubscriptions) {
        ?>
        <div class="dashboard-panel panel-overdue">
            <h2><?= translate('overdue_renewals', $i18n) ?></h2>
            <div class="dashboard-rows">
                    <?php

                    foreach ($overdueSubscriptions as $subscription) {
                        $subscriptionName = htmlspecialchars($subscription['name']);
                        $subscriptionPrice = $subscription['price'];
                        $subscriptionCurrency = $subscription['currency_id'];
                        $subscriptionNextPayment = $subscription['next_payment'];
                        $subscriptionDisplayNextPayment = formatDate($subscriptionNextPayment, $lang);
                        $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);

                        ?>
                            <button type="button" class="dashboard-row" onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                                <span class="dashboard-row-logo">
                                    <?php
                                    if (empty($subscription['logo'])) {
                                        echo '<span class="dashboard-row-initial">' . htmlspecialchars(mb_substr($subscription['name'], 0, 1)) . '</span>';
                                    } else {
                                        $subscriptionLogoSrc = "images/uploads/logos/" . $subscription['logo'];
                                        $subscriptionLogoVariantSrc = !empty($subscription['logo_variant']) ? "images/uploads/logos/" . $subscription['logo_variant'] : null;
                                        echo renderThemedLogoImg($subscriptionLogoSrc, $subscriptionLogoVariantSrc, $subscription['logo_text_color'] ?? null, 'subscription-item-logo', 'alt="' . $subscriptionName . ' logo" title="' . $subscriptionName . '"');
                                    }
                                    ?>
                                </span>
                                <span class="dashboard-row-copy">
                                    <strong><?= $subscriptionName ?></strong>
                                    <em><?= $subscriptionDisplayNextPayment ?></em>
                                </span>
                                <span class="dashboard-row-price"><?= $subscriptionDisplayPrice ?></span>
                            </button>
                        <?php
                    }
                    ?>
            </div>
        </div>
        <?php
    }
    ?>

    <div class="dashboard-layout">
        <div class="dashboard-main">
            <div class="dashboard-panel panel-upcoming">
                <h2><?= translate('upcoming_payments', $i18n) ?></h2>
                <div class="dashboard-rows">
                    <?php
                    if (empty($upcomingSubscriptions)) {
                        ?>
                        <p class="dashboard-empty"><?= translate('no_upcoming_payments', $i18n) ?></p>
                        <?php
                    } else {
                        foreach ($upcomingSubscriptions as $subscription) {
                            $subscriptionName = htmlspecialchars($subscription['name']);
                            $subscriptionPrice = $subscription['price'];
                            $subscriptionCurrency = $subscription['currency_id'];
                            $subscriptionNextPayment = $subscription['next_payment'];
                            $subscriptionDisplayNextPayment = formatDate($subscriptionNextPayment, $lang);
                            $subscriptionDisplayPrice = formatPrice($subscriptionPrice, $currencies[$subscriptionCurrency]['code'], $currencies);
                            ?>
                            <button type="button" class="dashboard-row" onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                                <span class="dashboard-row-logo">
                                    <?php
                                    if (empty($subscription['logo'])) {
                                        echo '<span class="dashboard-row-initial">' . htmlspecialchars(mb_substr($subscription['name'], 0, 1)) . '</span>';
                                    } else {
                                        $subscriptionLogoSrc = "images/uploads/logos/" . $subscription['logo'];
                                        $subscriptionLogoVariantSrc = !empty($subscription['logo_variant']) ? "images/uploads/logos/" . $subscription['logo_variant'] : null;
                                        echo renderThemedLogoImg($subscriptionLogoSrc, $subscriptionLogoVariantSrc, $subscription['logo_text_color'] ?? null, 'subscription-item-logo', 'alt="' . $subscriptionName . ' logo" title="' . $subscriptionName . '"');
                                    }
                                    ?>
                                </span>
                                <span class="dashboard-row-copy">
                                    <strong><?= $subscriptionName ?></strong>
                                    <em><?= $subscriptionDisplayNextPayment ?></em>
                                </span>
                                <span class="dashboard-row-price"><?= $subscriptionDisplayPrice ?></span>
                            </button>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <aside class="dashboard-side">
            <?php if (isset($totalCostPerMonth)) { ?>
            <div class="dashboard-panel panel-budget">
                <h2><?= translate('monthly_budget', $i18n) ?></h2>
                <dl class="dashboard-metrics">
                    <div>
                        <dt><?= translate("monthly_cost", $i18n) ?></dt>
                        <dd><?= CurrencyFormatter::format($totalCostPerMonth, $currencies[$userData['main_currency']]['code']) ?></dd>
                    </div>
                    <?php if (isset($monthlyBudget) && $monthlyBudget > 0) { ?>
                        <div>
                            <dt><?= translate("budget", $i18n) ?></dt>
                            <dd><?= formatPrice($monthlyBudget, $currencies[$userData['main_currency']]['code'], $currencies) ?></dd>
                        </div>
                        <?php if (isset($monthlyBudgetUsed)) { ?>
                            <div>
                                <dt><?= translate("budget_used", $i18n) ?></dt>
                                <dd><?= number_format($monthlyBudgetUsed, 2) ?>%</dd>
                            </div>
                        <?php } ?>
                        <div>
                            <dt><?= translate("budget_remaining", $i18n) ?></dt>
                            <dd><?= formatPrice($monthlyBudgetLeft, $currencies[$userData['main_currency']]['code'], $currencies) ?></dd>
                        </div>
                    <?php } ?>
                </dl>
            </div>
            <?php } ?>

            <?php if (isset($activeSubscriptions) && $activeSubscriptions > 0) { ?>
            <div class="dashboard-panel panel-overview">
                <h2><?= translate('your_subscriptions', $i18n) ?></h2>
                <dl class="dashboard-metrics">
                    <div>
                        <dt><?= translate('active_subscriptions', $i18n) ?></dt>
                        <dd><?= $activeSubscriptions ?></dd>
                    </div>
                    <?php if (isset($totalCostPerYear)) { ?>
                        <div>
                            <dt><?= translate('yearly_cost', $i18n) ?></dt>
                            <dd><?= CurrencyFormatter::format($totalCostPerYear, $currencies[$userData['main_currency']]['code']) ?></dd>
                        </div>
                    <?php } ?>
                    <?php if (isset($inactiveSubscriptions) && $inactiveSubscriptions > 0 && isset($totalSavingsPerMonth) && $totalSavingsPerMonth > 0) { ?>
                        <div>
                            <dt><?= translate('monthly_savings', $i18n) ?></dt>
                            <dd><?= CurrencyFormatter::format($totalSavingsPerMonth, $currencies[$userData['main_currency']]['code']) ?></dd>
                        </div>
                    <?php } ?>
                </dl>
            </div>
            <?php } ?>
        </aside>
    </div>

        <?php if (!empty($aiRecommendations)) { ?>
            <div class="dashboard-panel panel-wide ai-recommendations" style="margin-top:16px">
                <h2><?= translate('ai_recommendations', $i18n) ?></h2>
                <div class="ai-recommendations-container">
                    <ul class="ai-recommendations-list">
                        <?php

                        foreach ($aiRecommendations as $key => $recommendation) { ?>
                            <li class="ai-recommendation-item" data-id="<?= $recommendation['id'] ?>">
                                <div class="ai-recommendation-header">
                                    <h3>
                                        <span><?= ($key + 1) . ". " ?></span>
                                        <?= htmlspecialchars($recommendation['title']) ?>
                                    </h3>
                                    <span class="item-arrow-down fa fa-caret-down"></span>
                                </div>
                                <p class="collapsible"><?= htmlspecialchars($recommendation['description']) ?></p>
                                <p class="ai-recommendation-savings">
                                    <?= htmlspecialchars($recommendation['savings']) ?>
                                    <span>
                                        <a href="#" class="delete-ai-recommendation" title="<?= translate('delete', $i18n) ?>">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </span>
                                </p>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>

        <?php } ?>

</section>

<?php
// Get all subscriptions for user details lookup
$query = 'SELECT * FROM subscriptions WHERE user_id = :userId';
$stmt = $db->prepare($query);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$subscriptions = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $subscriptions[] = $row;
}
require_once 'includes/subscription_details_popup.php';
?>

<script src="scripts/dashboard.js?<?= $version ?>"></script>

<?php
require_once 'includes/footer.php';
?>