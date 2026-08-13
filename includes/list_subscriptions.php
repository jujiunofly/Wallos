<?php

require_once 'i18n/getlang.php';

function getBillingCycle($cycle, $frequency, $i18n)
{
    switch ($cycle) {
        case 1:
            return $frequency == 1 ? translate('Daily', $i18n) : $frequency . " " . translate('days', $i18n);
        case 2:
            return $frequency == 1 ? translate('Weekly', $i18n) : $frequency . " " . translate('weeks', $i18n);
        case 3:
            return $frequency == 1 ? translate('Monthly', $i18n) : $frequency . " " . translate('months', $i18n);
        case 4:
            return $frequency == 1 ? translate('Yearly', $i18n) : $frequency . " " . translate('years', $i18n);
        case 5:
            return translate('One-time', $i18n);
    }
}

function shiftSubscriptionDate(DateTimeImmutable $date, $cycle, $frequency, $direction)
{
    $frequency = max(1, abs((int) $frequency));
    $sign = $direction < 0 ? '-' : '+';

    switch ((int) $cycle) {
        case 1:
            return $date->modify("{$sign}{$frequency} days");
        case 2:
            return $date->modify("{$sign}{$frequency} weeks");
        case 4:
            return $date->modify("{$sign}{$frequency} years");
        case 3:
        default:
            return $date->modify("{$sign}{$frequency} months");
    }
}

function getSubscriptionProgress($cycle, $frequency, $next_payment, $start_date = null)
{
    $cycle = (int) $cycle;
    $frequency = (int) $frequency;

    if ($cycle === 5 || $frequency <= 0 || empty($next_payment)) {
        return 0;
    }

    try {
        $nextPaymentDate = new DateTimeImmutable($next_payment);
        $currentDate = new DateTimeImmutable('today');
    } catch (Exception $e) {
        return 0;
    }

    if ($currentDate >= $nextPaymentDate) {
        return 100;
    }

    // next_payment can be several cycles ahead (stale value or missed renewals).
    // Walk back one real billing cycle at a time so the window contains today.
    // Calendar months/years must be used here: a 3-month cycle is not 90 days,
    // and treating it as such walks back an extra cycle (e.g. Aug 12 → Nov 12).
    $periodStart = $nextPaymentDate;
    $guard = 0;
    while ($periodStart > $currentDate && $guard < 240) {
        $shifted = shiftSubscriptionDate($periodStart, $cycle, $frequency, -1);
        if ($shifted === false || $shifted >= $periodStart) {
            break;
        }
        $periodStart = $shifted;
        $guard++;
    }

    if (!empty($start_date)) {
        try {
            $startDate = new DateTimeImmutable($start_date);
            if ($startDate > $periodStart && $startDate < $nextPaymentDate) {
                $periodStart = $startDate;
            }
        } catch (Exception $e) {
            // Keep the cycle-derived period start.
        }
    }

    $periodDays = $periodStart->diff($nextPaymentDate)->days;
    if ($periodDays <= 0) {
        return 0;
    }

    $elapsedDays = $periodStart->diff($currentDate)->days;
    return (int) min(100, floor(($elapsedDays / $periodDays) * 100));
}

function getSubscriptionRemainingDays($next_payment)
{
    if (empty($next_payment)) {
        return 0;
    }
    try {
        $next = new DateTimeImmutable($next_payment);
        $today = new DateTimeImmutable('today');
        return (int) $today->diff($next)->format('%r%a');
    } catch (Exception $e) {
        return 0;
    }
}

function renderSubscriptionProgress($subscription, $style, $i18n, $extraClass = '')
{
    if (!empty($subscription['inactive'])) {
        return;
    }
    $progress = isset($subscription['progress']) ? (int) $subscription['progress'] : 0;
    if ($progress > 100) {
        $progress = 100;
    }
    if ($style === 'ring') {
        $days = isset($subscription['days_left'])
        ? (int) $subscription['days_left']
        : getSubscriptionRemainingDays($subscription['next_payment'] ?? '');
    $days = max(0, $days);
        $radius = 15.5;
        $circumference = 2 * M_PI * $radius;
        $offset = $circumference * (1 - ($progress / 100));
        ?>
        <span class="progress-ring" title="<?= htmlspecialchars($days . ' ' . translate('days', $i18n) . ' · ' . $progress . '%') ?>">
            <svg viewBox="0 0 36 36" aria-hidden="true">
                <circle class="progress-ring-bg" cx="18" cy="18" r="15.5"></circle>
                <circle class="progress-ring-fg" cx="18" cy="18" r="15.5"
                    stroke-dasharray="<?= number_format($circumference, 2, '.', '') ?>"
                    stroke-dashoffset="<?= number_format($offset, 2, '.', '') ?>"></circle>
            </svg>
            <span class="progress-ring-label"><?= (int) $days ?><small><?= translate('days_short', $i18n) ?></small></span>
        </span>
        <?php
        return;
    }
    ?>
    <div class="subscription-progress-container<?= $extraClass !== '' ? ' ' . htmlspecialchars($extraClass) : '' ?>">
        <span class="subscription-progress" style="width: <?= $progress ?>%;"></span>
    </div>
    <?php
}

function getPricePerMonth($cycle, $frequency, $price)
{
    switch ($cycle) {
        case 1:
            $numberOfPaymentsPerMonth = (30 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 2:
            $numberOfPaymentsPerMonth = (4.35 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 3:
            $numberOfPaymentsPerMonth = (1 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
        case 4:
            $numberOfMonths = (12 * $frequency);
            return $price / $numberOfMonths;
        case 5:
            return 0;
    }
}


function getPriceConverted($price, $currency, $database)
{
    $query = "SELECT rate FROM currencies WHERE id = :currency";
    $stmt = $database->prepare($query);
    $stmt->bindParam(':currency', $currency, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $exchangeRate = $result->fetchArray(SQLITE3_ASSOC);
    if ($exchangeRate === false) {
        return $price;
    } else {
        $fromRate = $exchangeRate['rate'];
        return $price / $fromRate;
    }
}

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

function printSubscriptions($subscriptions, $sort, $categories, $members, $i18n, $colorTheme, $imagePath, $disabledToBottom, $mobileNavigation, $showSubscriptionProgress, $currencies, $lang)
{
    global $settings;
    if ($sort === "price") {
        $priceDirection = function_exists('wallos_sort_direction') ? wallos_sort_direction('price') : 'DESC';
        usort($subscriptions, function ($a, $b) use ($priceDirection) {
            $cmp = ($a['price'] <=> $b['price']);
            return $priceDirection === 'DESC' ? -$cmp : $cmp;
        });
        if ($disabledToBottom === 'true') {
            usort($subscriptions, function ($a, $b) {
                return $a['inactive'] <=> $b['inactive'];
            });
        }
    }

    // Keep one-time purchases at the bottom without reshuffling the current sort.
    $regularSubscriptions = [];
    $oneTimeSubscriptions = [];
    foreach ($subscriptions as $subscription) {
        if (!empty($subscription['one_time'])) {
            $oneTimeSubscriptions[] = $subscription;
        } else {
            $regularSubscriptions[] = $subscription;
        }
    }
    $subscriptions = array_merge($regularSubscriptions, $oneTimeSubscriptions);

    $currentCategory = 0;
    $currentPayerUserId = 0;
    $currentPaymentMethodId = 0;
    $oneTimeSectionShown = false;
    foreach ($subscriptions as $subscription) {
        if ($subscription['one_time'] && !$oneTimeSectionShown) {
            ?>
            <div class="subscription-list-title">
                <?= translate('lifetime_purchases', $i18n) ?>
            </div>
            <?php
            $oneTimeSectionShown = true;
        }
        if ($sort == "category_id" && $subscription['category_id'] != $currentCategory) {
            ?>
            <div class="subscription-list-title">
                <?php
                if ($subscription['category_id'] == 1) {
                    echo translate('no_category', $i18n);
                } else {
                    echo $categories[$subscription['category_id']]['name'];
                }
                ?>
            </div>
            <?php
            $currentCategory = $subscription['category_id'];
        }
        if ($sort == "payer_user_id" && $subscription['payer_user_id'] != $currentPayerUserId) {
            ?>
            <div class="subscription-list-title">
                <?= $members[$subscription['payer_user_id']]['name'] ?>
            </div>
            <?php
            $currentPayerUserId = $subscription['payer_user_id'];
        }
        if ($sort == "payment_method_id" && $subscription['payment_method_id'] != $currentPaymentMethodId) {
            ?>
            <div class="subscription-list-title">
                <?= $subscription['payment_method_name'] ?>
            </div>
            <?php
            $currentPaymentMethodId = $subscription['payment_method_id'];
        }
        ?>
        <div class="subscription-container">
            <?php
            if ($mobileNavigation === 'true') {
                ?>
                <div class="mobile-actions" data-id="<?= $subscription['id'] ?>">
                    <button class="mobile-action-clone"></button>
                    <button class="mobile-action-clone" onClick="cloneSubscription(event, <?= $subscription['id'] ?>)">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/clone.php"; ?>
                        Clone
                    </button>
                    <button class="mobile-action-delete" onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/delete.php"; ?>
                        Delete
                    </button>
                    <?php
                    if ($subscription['auto_renew'] != 1 && !$subscription['one_time']) {
                        ?>
                        <button class="mobile-action-renew" onClick="renewSubscription(event, <?= $subscription['id'] ?>)">
                            <?php include $imagePath . "images/siteicons/svg/mobile-menu/renew.php"; ?>
                            Renew
                        </button>
                        <?php
                    }
                    ?>
                    <button class="mobile-action-edit" onClick="openEditSubscription(event, <?= $subscription['id'] ?>)">
                        <?php include $imagePath . "images/siteicons/svg/mobile-menu/edit.php"; ?>
                        Edit
                    </button>
                </div>
                <?php
            }

            $subscriptionExtraClasses = "";
            if ($subscription['inactive']) {
                $subscriptionExtraClasses .= " inactive";
            }
            if ($subscription['auto_renew'] != 1) {
                $subscriptionExtraClasses .= " manual";
            }

            $hasLogo = false;
            if ($subscription['logo'] != "") {
                $hasLogo = true;
            }

            ?>

            <div class="subscription<?= $subscriptionExtraClasses ?>"
                onClick="showSubscriptionDetails(event, <?= $subscription['id'] ?>)" data-id="<?= $subscription['id'] ?>"
                data-name="<?= $subscription['name'] ?>">
                <div class="subscription-main">
                    <span class="logo <?= !$hasLogo ? 'hideOnMobile' : '' ?>">
                        <?php
                        if ($hasLogo) {
                            echo renderThemedLogoImg($subscription['logo'], $subscription['logo_variant'] ?? null, $subscription['logo_text_color'] ?? null);
                        } else {
                            include $imagePath . "images/siteicons/svg/logo.php";
                        }
                        ?>
                    </span>
                    <span class="name <?= $hasLogo ? 'hideOnMobile' : '' ?>"><?= $subscription['name'] ?></span>
                    <span class="cycle"
                        title="<?= $subscription['one_time'] ? $subscription['billing_cycle'] : ($subscription['auto_renew'] ? translate("automatically_renews", $i18n) : translate("manual_renewal", $i18n)) ?>">
                        <?php
                        if (!$subscription['one_time']) {
                            if ($subscription['auto_renew']) {
                                include $imagePath . "images/siteicons/svg/automatic.php";
                            } else {
                                include $imagePath . "images/siteicons/svg/manual.php";
                            }
                        }
                        ?>
                        <?= $subscription['billing_cycle'] ?>
                    </span>
                    <span class="next"><?= formatDate($subscription['next_payment'], $lang) ?></span>
                    <span class="price">
                        <span class="value">
                            <?= formatPrice($subscription['price'], $subscription['currency_code'], $currencies) ?>
                            <?php
                            if (isset($subscription['original_price']) && $subscription['original_price'] != $subscription['price']) {
                                ?>
                                <span
                                    class="original_price">(<?= formatPrice($subscription['original_price'], $subscription['original_currency_code'], $currencies) ?>)</span>
                                <?php
                            }
                            ?>
                        </span>

                    </span>
                    <span class="payment_method">
                        <?php
                        if ($showSubscriptionProgress === 'true' && ($settings['subscription_progress_style'] ?? 'bar') === 'ring' && !$subscription['inactive']) {
                            renderSubscriptionProgress($subscription, 'ring', $i18n);
                        }
                        ?>
                        <img src="<?= $subscription['payment_method_icon'] ?>"
                            title="<?= translate('payment_method', $i18n) ?>: <?= $subscription['payment_method_name'] ?>" />
                    </span>
                    <?php
                    $desktopMenuButtonClass = ""; {
                    }
                    if ($mobileNavigation === "true") {
                        $desktopMenuButtonClass = "mobileNavigationHideOnMobile";
                    }
                    ?>
                    <button type="button" class="actions-expand <?= $desktopMenuButtonClass ?>"
                        onClick="expandActions(event, <?= $subscription['id'] ?>)">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="actions">
                        <li class="edit" title="<?= translate('edit_subscription', $i18n) ?>"
                            onClick="openEditSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <?= translate('edit_subscription', $i18n) ?>
                        </li>
                        <li class="delete" title="<?= translate('delete', $i18n) ?>"
                            onClick="deleteSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-trash-can"></i>
                            <?= translate('delete', $i18n) ?>
                        </li>
                        <li class="clone" title="<?= translate('clone', $i18n) ?>"
                            onClick="cloneSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-copy"></i>
                            <?= translate('clone', $i18n) ?>
                        </li>
                        <?php
                        if ($subscription['auto_renew'] != 1 && !$subscription['one_time']) {
                            ?>
                            <li class="renew" title="<?= translate('renew', $i18n) ?>"
                                onClick="renewSubscription(event, <?= $subscription['id'] ?>)">
                                <i class="fa-solid fa-rotate-right"></i>
                                <?= translate('renew', $i18n) ?>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                </div>
                <div class="subscription-back" inert>
                    <button type="button" class="subscription-back-close"
                        onClick="event.stopPropagation(); unflipCard(<?= $subscription['id'] ?>)"
                        title="<?= translate('cancel', $i18n) ?>">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <button type="button" class="back-action"
                        onClick="unflipCard(<?= $subscription['id'] ?>); openEditSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <?= translate('edit_subscription', $i18n) ?>
                    </button>
                    <button type="button" class="back-action"
                        onClick="unflipCard(<?= $subscription['id'] ?>); cloneSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-copy"></i>
                        <?= translate('clone', $i18n) ?>
                    </button>
                    <?php
                    if ($subscription['auto_renew'] != 1 && !$subscription['one_time']) {
                        ?>
                        <button type="button" class="back-action"
                            onClick="unflipCard(<?= $subscription['id'] ?>); renewSubscription(event, <?= $subscription['id'] ?>)">
                            <i class="fa-solid fa-rotate-right"></i>
                            <?= translate('renew', $i18n) ?>
                        </button>
                        <?php
                    }
                    ?>
                    <button type="button" class="back-action delete"
                        onClick="unflipCard(<?= $subscription['id'] ?>); deleteSubscription(event, <?= $subscription['id'] ?>)">
                        <i class="fa-solid fa-trash-can"></i>
                        <?= translate('delete', $i18n) ?>
                    </button>
                </div>
            </div>
            <?php
            if ($showSubscriptionProgress === 'true' && !$subscription['inactive'] && ($settings['subscription_progress_style'] ?? 'bar') !== 'ring') {
                renderSubscriptionProgress($subscription, 'bar', $i18n);
            }
            ?>
        </div>
        <?php
    }
}

$query = "SELECT main_currency FROM user WHERE id = :userId";
$stmt = $db->prepare($query);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
if ($row !== false) {
    $mainCurrencyId = $row['main_currency'];
} else {
    $mainCurrencyId = $currencies[1]['id'];
}

?>