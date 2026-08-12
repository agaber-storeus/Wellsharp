<?php

namespace App\Services;

class StudentSurveyDefinition
{
    /** @return array<int, array{key: string, label: string, type: string, options?: array<int, string>, required: bool}> */
    public function questions(): array
    {
        $rating = ['1: Poor', '2: Fair', '3: Average', '4: Good', '5: Excellent'];
        $yesNo = ['Yes', 'No'];
        $yesNoAssessed = ['Yes', 'No', 'Not yet assessed'];

        return [
            ['key' => 'job_position', 'label' => 'What is your current job position?', 'type' => 'select', 'options' => ['Assistant Driller', 'Driller', 'Derrickman', 'Toolpusher', 'Well Control Engineers', 'Well Control Specialists', 'Well Control Trainers', 'Other'], 'required' => true],
            ['key' => 'recommend_provider', 'label' => 'Would you recommend this training provider to your work colleagues?', 'type' => 'select', 'options' => $yesNo, 'required' => true],
            ['key' => 'classroom_facilities', 'label' => 'Please rate the classroom facilities.', 'type' => 'select', 'options' => $rating, 'required' => true],
            ['key' => 'instructor_name', 'label' => 'What is the name of the person who taught your class?', 'type' => 'text', 'required' => true],
            ['key' => 'instructor_overall', 'label' => 'How do you rate the instructor overall?', 'type' => 'select', 'options' => $rating, 'required' => true],
            ['key' => 'instructor_knowledge', 'label' => "How do you rate the instructor's knowledge of the subject matter?", 'type' => 'select', 'options' => $rating, 'required' => true],
            ['key' => 'instructor_effort', 'label' => "How do you rate the instructor's effort to ensure all trainees learned the material?", 'type' => 'select', 'options' => $rating, 'required' => true],
            ['key' => 'instructor_preparedness', 'label' => "How do you rate the instructor's preparedness?", 'type' => 'select', 'options' => $rating, 'required' => true],
            ['key' => 'instructor_questions', 'label' => "How do you rate your instructor's openness to questions from trainees?", 'type' => 'select', 'options' => $rating, 'required' => true],
            ['key' => 'course_material', 'label' => 'How do you rate the course material (handouts, presentations, visual aids)?', 'type' => 'select', 'options' => $rating, 'required' => true],
            ['key' => 'simulator_count', 'label' => 'If applicable, how many simulators were there available for trainees to use during the course?', 'type' => 'select', 'options' => ['No Available Simulators', '1 Available Simulator', '2 Available Simulators', '3 Available Simulators', 'More than 3 Simulators', 'Not yet assessed'], 'required' => true],
            ['key' => 'simulator_time', 'label' => 'If applicable, how much total time did you get to spend controlling the simulator?', 'type' => 'select', 'options' => ['0 Hours', '1 Hour', '2 Hours', '3 Hours', '4 or More Hours', 'Not yet assessed'], 'required' => true],
            ['key' => 'practical_assessment', 'label' => 'Did you receive a practical assessment on either a simulator or live well?', 'type' => 'select', 'options' => $yesNoAssessed, 'required' => true],
            ['key' => 'simulator_group', 'label' => 'How many trainees were allowed to use each simulator at the same time?', 'type' => 'select', 'options' => ['Between 1-3 Trainees', 'More than 4 Trainees', 'Not yet assessed'], 'required' => true],
            ['key' => 'assessment_time', 'label' => 'Approximately how much time was devoted to your practical assessment?', 'type' => 'select', 'options' => ['0 Minutes', '15 Minutes', '30 Minutes', '45 Minutes', 'More than 45 Minutes'], 'required' => true],
            ['key' => 'solo_supervisor_assessment', 'label' => 'When you did your Supervisor practical assessment were you the only person being assessed on the simulator?', 'type' => 'select', 'options' => $yesNoAssessed, 'required' => true],
            ['key' => 'simulator_adequacy', 'label' => 'Did you find your simulator adequate for all of the practical exercise?', 'type' => 'select', 'options' => $yesNoAssessed, 'required' => true],
            ['key' => 'proctor_present', 'label' => 'Is there a Proctor (Invigilator) present to administer the final test?', 'type' => 'select', 'options' => $yesNo, 'required' => true],
            ['key' => 'additional_comments', 'label' => 'Additional Comments', 'type' => 'textarea', 'required' => false],
        ];
    }
}
