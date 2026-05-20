@php
    $studentName = data_get($student, 'full_name') ?: 'Guest Student';
    $initial = strtoupper(\Illuminate\Support\Str::substr($studentName, 0, 1));
@endphp
<div class="sidebar-brand">
    <h2><i class="fa-solid fa-university"></i> EntryEase</h2>
    <p>Student Portal</p>
</div>
<div class="student-user">
    <div class="student-avatar" aria-hidden="true">{{ $initial }}</div>
    <div class="student-name">{{ $studentName }}</div>
</div>
<nav>
    <ul class="sidebar-menu">
        <li><a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'active' : '' }}"><i class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a></li>
        <li><a href="{{ route('student.apply') }}" class="{{ request()->routeIs('student.apply') ? 'active' : '' }}"><i class="fa-solid fa-file-pen"></i><span>Apply</span></a></li>
        <li><a href="{{ route('student.applications') }}" class="{{ request()->routeIs('student.applications') ? 'active' : '' }}"><i class="fa-solid fa-clipboard-list"></i><span>My Application</span></a></li>
        <li><a href="{{ route('student.exam.schedule') }}" class="{{ request()->routeIs('student.exam.schedule') ? 'active' : '' }}"><i class="fa-solid fa-calendar-days"></i><span>Exam Schedule</span></a></li>
        <li><a href="{{ route('student.exam.take') }}" class="{{ request()->routeIs('student.exam.take') ? 'active' : '' }}"><i class="fa-solid fa-pen-to-square"></i><span>Take Exam</span></a></li>
        <li><a href="{{ route('student.results') }}" class="{{ request()->routeIs('student.results') ? 'active' : '' }}"><i class="fa-solid fa-chart-line"></i><span>Results</span></a></li>
    </ul>
</nav>
