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

// Fetch the next upcoming subscriptions
$stmt = $db->prepare("SELECT id, logo, logo_text_color, logo_variant, name, price, currency_id, next_payment, inactive FROM subscriptions WHERE user_id = :userId AND next_payment >= date('now') AND next_payment <= date('now', '+30 days') AND inactive = 0 AND cycle != 5 ORDER BY next_payment ASC LIMIT 12");
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
    <div class="dashboard-panel dashboard-shell">
        <div class="dashboard-hero">
            <div>
                <p class="dashboard-kicker"><?= translate('dashboard', $i18n) ?></p>
                <h1><?= translate('hello', $i18n) ?> <?= htmlspecialchars($first_name) ?></h1>
            </div>
        </div>

        <div class="dashboard-kpis">
            <?php
            $kpiTone = 0;
            if (isset($totalCostPerMonth)) {
                $kpiTone++;
                ?>
                <div class="dashboard-kpi kpi-tone-<?= $kpiTone ?>">
                    <span><?= translate('monthly_cost', $i18n) ?></span>
                    <strong><?= CurrencyFormatter::format($totalCostPerMonth, $heroCurrencyCode) ?></strong>
                </div>
                <?php
            }
            if (isset($activeSubscriptions)) {
                $kpiTone++;
                ?>
                <div class="dashboard-kpi kpi-tone-<?= $kpiTone ?>">
                    <span><?= translate('active_subscriptions', $i18n) ?></span>
                    <strong><?= (int) $activeSubscriptions ?></strong>
                </div>
                <?php
            }
            if (isset($totalCostPerYear)) {
                $kpiTone++;
                ?>
                <div class="dashboard-kpi kpi-tone-<?= $kpiTone ?>">
                    <span><?= translate('yearly_cost', $i18n) ?></span>
                    <strong><?= CurrencyFormatter::format($totalCostPerYear, $heroCurrencyCode) ?></strong>
                </div>
                <?php
            }
            $kpiTone++;
            ?>
            <div class="dashboard-kpi kpi-tone-<?= $kpiTone ?>">
                <span><?= translate('next_payment', $i18n) ?></span>
                <strong title="<?= htmlspecialchars($heroNextName) ?>"><?= htmlspecialchars($heroNextPayment) ?></strong>
            </div>
            <?php if (isset($monthlyBudget) && $monthlyBudget > 0 && isset($monthlyBudgetLeft)) {
                $kpiTone++;
                ?>
                <div class="dashboard-kpi kpi-tone-<?= $kpiTone ?>">
                    <span><?= translate('budget_remaining', $i18n) ?></span>
                    <strong><?= formatPrice($monthlyBudgetLeft, $heroCurrencyCode, $currencies) ?></strong>
                    <?php if (isset($monthlyBudgetUsed)) { ?>
                        <em><?= number_format($monthlyBudgetUsed, 1) ?>% <?= translate('budget_used', $i18n) ?></em>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if (isset($inactiveSubscriptions) && $inactiveSubscriptions > 0 && isset($totalSavingsPerMonth) && $totalSavingsPerMonth > 0) {
                $kpiTone++;
                ?>
                <div class="dashboard-kpi kpi-tone-<?= $kpiTone ?>">
                    <span><?= translate('monthly_savings', $i18n) ?></span>
                    <strong><?= CurrencyFormatter::format($totalSavingsPerMonth, $heroCurrencyCode) ?></strong>
                </div>
            <?php } ?>
        </div>

        <?php if ($hasOverdueSubscriptions) { ?>
            <div class="dashboard-block dashboard-overdue">
                <h2><?= translate('overdue_renewals', $i18n) ?></h2>
                <div class="dashboard-rows">
                    <?php foreach ($overdueSubscriptions as $subscription) {
                        $subscriptionName = htmlspecialchars($subscription['name']);
                        $subscriptionDisplayNextPayment = formatDate($subscription['next_payment'], $lang);
                        $subscriptionDisplayPrice = formatPrice($subscription['price'], $currencies[$subscription['currency_id']]['code'], $currencies);
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
                    <?php } ?>
                </div>
            </div>
        <?php } ?>

        <div class="dashboard-block">
            <h2><?= translate('upcoming_payments', $i18n) ?></h2>
            <div class="dashboard-rows dashboard-timeline<?= count($upcomingSubscriptions) > 6 ? ' is-split' : '' ?>" style="--timeline-rows: <?= max(1, (int) ceil(count($upcomingSubscriptions) / 2)) ?>;">
                <?php
                if (empty($upcomingSubscriptions)) {
                    echo '<p class="dashboard-empty">' . translate('no_upcoming_payments', $i18n) . '</p>';
                } else {
                    foreach ($upcomingSubscriptions as $index => $subscription) {
                        $subscriptionName = htmlspecialchars($subscription['name']);
                        $subscriptionDisplayNextPayment = formatDate($subscription['next_payment'], $lang);
                        $subscriptionDisplayPrice = formatPrice($subscription['price'], $currencies[$subscription['currency_id']]['code'], $currencies);
                        $step = $index + 1;
                        ?>
                        <div class="dashboard-timeline-item<?= $index >= 6 ? ' is-extra' : '' ?>">
                            <button type="button" class="dashboard-row" onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>">
                                <span class="dashboard-timeline-step"><?= $step ?></span>
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
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
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