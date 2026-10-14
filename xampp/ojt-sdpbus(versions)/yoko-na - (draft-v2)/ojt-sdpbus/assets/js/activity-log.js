document.addEventListener('DOMContentLoaded', () => {
    const filterButtons = document.querySelectorAll('[data-activity-filter]');
    const searchInput = document.getElementById('activitySearch');
    const entries = Array.from(document.querySelectorAll('.activity-event'));
    const dayGroups = document.querySelectorAll('.activity-day-group');
    const noResults = document.getElementById('activityNoResults');
    const pagination = document.getElementById('activityPagination');
    const pageSummary = document.getElementById('activityPageSummary');
    const previousButton = document.getElementById('activityPrevious');
    const nextButton = document.getElementById('activityNext');
    const pageNumbers = document.getElementById('activityPageNumbers');
    const activeUserList = document.getElementById('activityUserList');
    const activityLayout = document.querySelector('.activity-layout');
    const pageSize = 6;
    let activeFilter = sessionStorage.getItem('sdpbusActivityFilter') ?? 'all';
    let currentPage = 1;

    const savedSearch = sessionStorage.getItem('sdpbusActivitySearch');
    if (searchInput && savedSearch !== null) searchInput.value = savedSearch;

    const localizeActivityTimes = () => {
        const timeFormat = new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });

        document.querySelectorAll('.activity-event-time[datetime]').forEach(timeElement => {
            const timestamp = new Date(timeElement.dateTime);
            if (!Number.isNaN(timestamp.getTime())) {
                timeElement.textContent = timeFormat.format(timestamp);
            }
        });
    };

    localizeActivityTimes();

    filterButtons.forEach(button => {
        const selected = button.dataset.activityFilter === activeFilter;
        button.classList.toggle('is-active', selected);
        button.setAttribute('aria-pressed', String(selected));
    });

    const refreshActiveUsers = async () => {
        if (!activeUserList) return;

        try {
            const response = await fetch('/ojt-sdpbus/backend/user/activity_summary.php', {
                headers: { Accept: 'application/json' },
                cache: 'no-store'
            });
            if (!response.ok) return;

            const data = await response.json();
            const latestActivityId = Number(data.latest_activity_id);
            const renderedActivityId = Number(activityLayout?.dataset.latestActivityId ?? 0);
            if (latestActivityId > renderedActivityId) {
                sessionStorage.setItem('sdpbusActivityFilter', activeFilter);
                sessionStorage.setItem('sdpbusActivitySearch', searchInput?.value ?? '');
                window.location.reload();
                return;
            }

            const users = Array.isArray(data.users) ? data.users : [];
            const maxCount = Math.max(1, ...users.map(user => Number(user.activity_count) || 0));
            const fragment = document.createDocumentFragment();

            if (users.length === 0) {
                const emptyMessage = document.createElement('p');
                emptyMessage.className = 'activity-muted';
                emptyMessage.textContent = 'No user activity yet.';
                fragment.append(emptyMessage);
            }

            users.forEach((user, index) => {
                const username = String(user.username ?? 'System');
                const count = Number(user.activity_count) || 0;
                const row = document.createElement('div');
                row.className = 'activity-user-row';

                if (typeof user.profile_image === 'string' && user.profile_image.startsWith('/ojt-sdpbus/uploads/avatars/')) {
                    const avatar = document.createElement('img');
                    avatar.className = 'activity-user-avatar';
                    avatar.src = user.profile_image;
                    avatar.alt = '';
                    avatar.loading = 'lazy';
                    row.append(avatar);
                } else {
                    const initials = document.createElement('span');
                    initials.className = 'activity-user-initials';
                    initials.setAttribute('aria-hidden', 'true');
                    initials.textContent = username.slice(0, 2).toUpperCase();
                    row.append(initials);
                }

                const name = document.createElement('strong');
                name.textContent = username;
                row.append(name);

                const bar = document.createElement('span');
                bar.className = 'activity-user-bar';
                bar.setAttribute('aria-label', `${count} activities`);
                const barFill = document.createElement('span');
                barFill.style.width = `${Math.round((count / maxCount) * 100)}%`;
                bar.append(barFill);
                row.append(bar);

                const countLabel = document.createElement('span');
                countLabel.className = 'activity-user-count';
                countLabel.textContent = String(count);
                row.append(countLabel);
                row.style.setProperty('--activity-user-index', String(index));
                fragment.append(row);
            });

            activeUserList.replaceChildren(fragment);
        } catch (error) {
            console.error('Unable to refresh active users:', error);
        }
    };

    const addPageButton = (page) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'activity-page-button activity-page-number';
        button.textContent = String(page);
        button.setAttribute('aria-label', `Page ${page}`);
        button.setAttribute('aria-current', page === currentPage ? 'page' : 'false');
        button.classList.toggle('is-current', page === currentPage);
        button.addEventListener('click', () => {
            currentPage = page;
            applyFilters();
        });
        pageNumbers.append(button);
    };

    const applyFilters = () => {
        const query = (searchInput?.value ?? '').trim().toLowerCase();
        const matchingEntries = entries.filter(entry => {
            const matchesCategory = activeFilter === 'all' || entry.dataset.category === activeFilter;
            const matchesSearch = !query || (entry.dataset.search ?? '').includes(query);
            return matchesCategory && matchesSearch;
        });
        const totalPages = Math.max(1, Math.ceil(matchingEntries.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const pageStart = (currentPage - 1) * pageSize;
        const pageEntries = new Set(matchingEntries.slice(pageStart, pageStart + pageSize));

        entries.forEach(entry => {
            entry.hidden = !pageEntries.has(entry);
        });

        dayGroups.forEach(group => {
            group.hidden = !group.querySelector('.activity-event:not([hidden])');
        });

        if (noResults) noResults.hidden = matchingEntries.length > 0;
        if (pagination) pagination.hidden = matchingEntries.length === 0;
        if (pageSummary) {
            const firstResult = matchingEntries.length ? pageStart + 1 : 0;
            const lastResult = Math.min(pageStart + pageSize, matchingEntries.length);
            pageSummary.textContent = `Showing ${firstResult}-${lastResult} of ${matchingEntries.length} activities`;
        }
        if (previousButton) previousButton.disabled = currentPage === 1;
        if (nextButton) nextButton.disabled = currentPage === totalPages || matchingEntries.length === 0;

        if (pageNumbers) {
            pageNumbers.replaceChildren();
            const firstPage = Math.max(1, Math.min(currentPage - 2, totalPages - 4));
            const lastPage = Math.min(totalPages, firstPage + 4);

            if (firstPage > 1) {
                addPageButton(1);
                if (firstPage > 2) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'activity-page-ellipsis';
                    ellipsis.textContent = '...';
                    pageNumbers.append(ellipsis);
                }
            }

            for (let page = firstPage; page <= lastPage; page++) {
                addPageButton(page);
            }

            if (lastPage < totalPages) {
                if (lastPage < totalPages - 1) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'activity-page-ellipsis';
                    ellipsis.textContent = '...';
                    pageNumbers.append(ellipsis);
                }
                addPageButton(totalPages);
            }
        }
    };

    previousButton?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            applyFilters();
        }
    });

    nextButton?.addEventListener('click', () => {
        currentPage++;
        applyFilters();
    });

    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.activityFilter ?? 'all';
            sessionStorage.setItem('sdpbusActivityFilter', activeFilter);
            currentPage = 1;
            filterButtons.forEach(filterButton => {
                const selected = filterButton === button;
                filterButton.classList.toggle('is-active', selected);
                filterButton.setAttribute('aria-pressed', String(selected));
            });
            applyFilters();
        });
    });

    searchInput?.addEventListener('input', () => {
        sessionStorage.setItem('sdpbusActivitySearch', searchInput.value);
        currentPage = 1;
        applyFilters();
    });

    applyFilters();
    refreshActiveUsers();
    window.setInterval(() => {
        if (!document.hidden) refreshActiveUsers();
    }, 10000);
});