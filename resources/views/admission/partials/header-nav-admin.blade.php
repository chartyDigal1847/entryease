<a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Home</span></a>
<a href="{{ route('admin.applications') }}" class="{{ request()->routeIs('admin.applications') ? 'active' : '' }}"><i class="fa-solid fa-file-lines" aria-hidden="true"></i><span>Applications</span></a>
<a href="{{ route('exam.schedules') }}" class="{{ request()->routeIs('exam.*') ? 'active' : '' }}"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i><span>Exam Schedules</span></a>
<a href="{{ route('admin.registrars') }}" class="{{ request()->routeIs('admin.registrars*') ? 'active' : '' }}"><i class="fa-solid fa-user-tie" aria-hidden="true"></i><span>Admission Officers</span></a>
