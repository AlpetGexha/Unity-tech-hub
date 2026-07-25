<?php

namespace App\Providers;

use App\Ai\Agents\CandidateAnalyzer;
use App\Ai\Agents\InterviewGrader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Audio;
use Laravel\Ai\Transcription;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDemoFakeAi();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Fake every AI-calling class when the offline demo kill switch is on, so the
     * app keeps rendering valid data if OpenAI or the venue network is unavailable.
     *
     * InterviewGrader (Task 9) is faked defensively via class_exists() since it does
     * not exist yet — this boot hook will pick it up automatically once it lands.
     */
    protected function configureDemoFakeAi(): void
    {
        if (! config('app.demo_fake_ai')) {
            return;
        }

        CandidateAnalyzer::fake();
        Audio::fake();
        Transcription::fake();

        if (class_exists(InterviewGrader::class)) {
            InterviewGrader::fake();
        }
    }
}
