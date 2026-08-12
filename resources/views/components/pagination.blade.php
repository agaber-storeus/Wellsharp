@if($paginator->hasPages())
    <nav aria-label="Pagination"><ul class="pagination">
        @foreach($elements as $element)
            @if(is_string($element))<li><span>{{ $element }}</span></li>
            @else @foreach($element as $page => $url)<li class="{{ $page == $paginator->currentPage() ? 'active' : '' }}">@if($page == $paginator->currentPage())<span>{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif</li>@endforeach @endif
        @endforeach
    </ul></nav>
@endif
