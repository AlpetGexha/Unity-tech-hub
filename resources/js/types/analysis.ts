/**
 * Mirrors the `analyses.result` JSON contract produced by the backend.
 *
 * `overall`, `skills`, `claims` and `interview_questions` come from the model;
 * every other key is merged in server-side after the call.
 */

export type Recommendation = 'strong_yes' | 'yes' | 'maybe' | 'no';

export type ClaimStatus = 'verified' | 'weak' | 'unsupported';

export type ClaimSource = 'cv' | 'github_profile' | 'repo' | 'source_file';

export type AnalysisOverall = {
    /** 0-100 */
    fit_score: number;
    recommendation: Recommendation;
    summary: string;
};

export type AnalysisSkill = {
    name: string;
    /** 0-100 */
    score: number;
    /** 0-100, share of the rubric this skill carries */
    weight: number;
    reasoning: string;
};

export type AnalysisClaim = {
    claim: string;
    status: ClaimStatus;
    /** Exact quote from the CV, GitHub profile or source file */
    evidence: string;
    source: ClaimSource;
    /** e.g. `user/repo/path:42`, or an empty string when there is no location */
    source_ref: string;
};

export type InterviewQuestion = {
    question: string;
    targets_skill: string;
    /** e.g. `user/repo/path:L22-L58`, or `job_requirement` */
    grounded_in: string;
    ideal_answer_points: string[];
};

export type RubricCriterion = {
    name: string;
    /** 0-100 */
    weight: number;
    description: string;
};

export type Rubric = {
    criteria: RubricCriterion[];
    scale: string;
};

export type Analysis = {
    candidate_id: string;
    job_id: string;
    /** ISO-8601 */
    generated_at: string;
    overall: AnalysisOverall;
    skills: AnalysisSkill[];
    claims: AnalysisClaim[];
    interview_questions: InterviewQuestion[];
    rubric: Rubric;
    fairness_note: string;
};
