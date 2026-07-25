<?php

namespace App;

enum CandidateInterviewStatus: string
{
    case WaitingForInterview = 'waiting_for_interview';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Interviewed = 'interviewed';
}
