<div class="sidebar-brand">
    <h2><i class="fa-solid fa-university"></i> EntryEase</h2>
    <p>Admission Officer</p>
</div>
@isset($userContext)
    <div class="student-user">
        <div class="student-avatar">{{ substr($userContext['name'] ?? 'G', 0, 1) }}</div>
        <div class="student-name">{{ $userContext['name'] ?? 'Guest' }}</div>
    </div>
@endisset
<nav>
    <ul class="sidebar-menu">
        <li><a href="{{ route('registrar.dashboard') }}"
               class="{{ request()->routeIs('registrar.dashboard') ? 'active' : '' }}">
            <i class="fa-solid fa-house"></i><span>Dashboard</span>
        </a></li>

        <li><a href="{{ route('registrar.applications') }}"
               class="{{ request()->routeIs('registrar.applications') || request()->routeIs('registrar.application.*') ? 'active' : '' }}">
            <i class="fa-solid fa-clipboard-list"></i><span>Review Queue</span>
        </a></li>

        {{-- Exam section --}}
        <li class="sidebar-section-label">Exam Management</li>

        <li><a href="{{ route('exam.schedules') }}"
               class="{{ request()->routeIs('exam.schedules') ? 'active' : '' }}">
            <i class="fa-solid fa-calendar-days"></i><span>Schedules</span>
        </a></li>

        <li><a href="{{ route('exam.schedules.create') }}"
               class="{{ request()->routeIs('exam.schedules.create') ? 'active' : '' }}">
            <i class="fa-solid fa-calendar-plus"></i><span>New Schedule</span>
        </a></li>

        {{-- Dynamic: show question links for each schedule --}}
        @php
            $schedules = \App\Models\ExamSchedule::orderBy('exam_date')->get(['id','title','status']);
        @endphp
        @foreach($schedules as $sch)
        <li><a href="{{ route('exam.schedules.questions', $sch) }}"
               class="{{ request()->routeIs('exam.schedules.questions') && request()->route('schedule')?->id == $sch->id ? 'active' : '' }}"
               title="{{ $sch->title }}">
            <i class="fa-solid fa-list-check"></i>
            <span class="sidebar-truncate">{{ Str::limit($sch->title, 22) }}</span>
        </a></li>
        @endforeach

        @if($schedules->isEmpty())
        <li class="sidebar-hint">
            <i class="fa-solid fa-circle-info"></i>
            <span>Create a schedule to add questions.</span>
        </li>
        @endif
    </ul>
</nav>
