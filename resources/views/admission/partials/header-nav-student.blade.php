<a href="{{ route('student.dashboard') }}" class="{{ request()->routeIs('student.dashboard') ? 'active' : '' }}"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><span>Dashboard</span></a>
<a href="{{ route('student.apply') }}" class="{{ request()->routeIs('student.apply') ? 'active' : '' }}"><i class="fa-solid fa-file-pen" aria-hidden="true"></i><span>Apply</span></a>
<a href="{{ route('student.applications') }}" class="{{ request()->routeIs('student.applications') ? 'active' : '' }}"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i><span>My Application</span></a>
<a href="{{ route('student.exam.schedule') }}" class="{{ request()->routeIs('student.exam.schedule') ? 'active' : '' }}"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><span>Exam Schedule</span></a>
<a href="{{ route('student.exam.take') }}" class="{{ request()->routeIs('student.exam.take') ? 'active' : '' }}"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i><span>Take Exam</span></a>
<a href="{{ route('student.results') }}" class="{{ request()->routeIs('student.results') ? 'active' : '' }}"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span>Results</span></a>
