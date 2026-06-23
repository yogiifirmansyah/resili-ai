<?php

use Tests\MySqlTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature/HealthCheckTest.php');

pest()->extend(TestCase::class)->in('Feature/FeedbackFailoverTest.php');

pest()->extend(TestCase::class)->in('Feature/SyncFallbackFeedbackTest.php');

pest()->extend(MySqlTestCase::class)->in('Feature/FeedbackSubmissionTest.php');

pest()->extend(TestCase::class)->in('Feature/ProcessFeedbackAITest.php');
