/**
 * StudentHub Global Profile Synchronization
 * Ensures the student's name, ID, and CGPA are reflected across all pages.
 */
(function () {
    const DEFAULT_CGPA = '8.33';

    function getInitials(name) {
        if (!name) return 'SH';
        const parts = name.trim().split(/\s+/);
        let inits = parts[0] ? parts[0].charAt(0).toUpperCase() : '';
        if (parts.length > 1) {
            inits += parts[parts.length - 1].charAt(0).toUpperCase();
        }
        return inits || 'SH';
    }

    function applyProfileToPage(profile) {
        if (!profile) return;

        const fullName = profile.full_name || profile.name;
        const studentId = profile.student_id || '25CS033';
        const semester = profile.semester || '3';
        const branch = profile.branch || 'Computer Science & Engineering';
        const cgpa = profile.cgpa || DEFAULT_CGPA;

        // 1. Update Sidebar Profile Name & Subtitle on all pages
        const sidebarProfiles = document.querySelectorAll('.sidebar .profile');
        sidebarProfiles.forEach(function (box) {
            const h3 = box.querySelector('h3');
            if (h3 && fullName) {
                h3.textContent = fullName;
            }

            // Update student ID and semester paragraph without removing sign out link
            const paragraphs = box.querySelectorAll('p');
            if (paragraphs.length > 0) {
                const subP = paragraphs[0];
                if (subP && !subP.querySelector('a')) {
                    subP.textContent = studentId + ' • Semester ' + semester;
                }
            }
        });

        // Specific IDs if on profile page or other customized views
        const sidebarName = document.getElementById('sidebarName');
        if (sidebarName && fullName) sidebarName.textContent = fullName;

        const sidebarSubtitle = document.getElementById('sidebarSubtitle');
        if (sidebarSubtitle) sidebarSubtitle.textContent = studentId + ' • Semester ' + semester;

        // 2. Update Greeting on Homepage (index.html)
        const welcomeH1s = document.querySelectorAll('h1');
        welcomeH1s.forEach(function (h1) {
            if (h1.textContent.includes('Hi,') && fullName) {
                const firstName = fullName.trim().split(/\s+/)[0];
                h1.innerHTML = 'Hi, ' + escapeHtml(firstName) + ' <span>👋</span>';
            }
        });

        // 3. Update Homepage Welcome Subtitle
        const welcomeSub = document.querySelector('.welcome-banner p');
        if (welcomeSub) {
            welcomeSub.textContent = studentId + ' • ' + (branch.includes('CSE') || branch.includes('Computer') ? 'B.Tech CSE' : branch) + ' • Semester ' + semester;
        }

        // 4. Update Profile page elements if present
        const viewFullName = document.getElementById('viewFullName');
        if (viewFullName && fullName) viewFullName.textContent = fullName;

        const viewName = document.getElementById('viewName');
        if (viewName && fullName) viewName.textContent = fullName;

        const viewStudentId = document.getElementById('viewStudentId');
        if (viewStudentId) viewStudentId.textContent = studentId;

        const viewBranch = document.getElementById('viewBranch');
        if (viewBranch) viewBranch.textContent = branch;

        const viewSemester = document.getElementById('viewSemester');
        if (viewSemester) {
            const sfx = { 1: 'st', 2: 'nd', 3: 'rd' }[semester] || 'th';
            viewSemester.textContent = semester + sfx + ' Semester';
        }

        const viewHeaderSub = document.getElementById('viewHeaderSub');
        if (viewHeaderSub) viewHeaderSub.textContent = studentId + ' · ' + branch;

        // 5. Update Avatar Initials
        const avatars = document.querySelectorAll('.avatar, #avatarDisplay');
        const initials = getInitials(fullName);
        avatars.forEach(function (av) {
            av.textContent = initials;
        });

        // 6. Update CGPA everywhere
        // A) Homepage (index.html) stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(function (card) {
            const span = card.querySelector('span');
            const strong = card.querySelector('strong');
            if (span && strong && span.textContent.toLowerCase().includes('cgpa')) {
                strong.textContent = cgpa;
            }
        });

        // B) Results page (results.html) CGPA cards
        const cgpaCards = document.querySelectorAll('.cgpa-card');
        cgpaCards.forEach(function (card) {
            const p = card.querySelector('p');
            const h2 = card.querySelector('h2');
            if (p && h2 && p.textContent.toLowerCase().includes('cumulative gpa')) {
                h2.textContent = cgpa;
            }
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Load and apply immediately from localStorage
    function loadFromLocalStorage() {
        try {
            const stored = localStorage.getItem('studenthub_profile');
            if (stored) {
                const data = JSON.parse(stored);
                applyProfileToPage(data);
                return data;
            } else {
                const storedName = localStorage.getItem('studenthub_profile_name');
                if (storedName) {
                    const fallback = { full_name: storedName, cgpa: DEFAULT_CGPA };
                    applyProfileToPage(fallback);
                    return fallback;
                }
            }
        } catch (e) {
            console.error('Failed to parse profile from localStorage', e);
        }
        return null;
    }

    // Sync from server session/database if available
    function fetchLiveProfile() {
        fetch('api_profile.php', { cache: 'no-store' })
            .then(function (res) {
                if (!res.ok) throw new Error('Network error');
                return res.json();
            })
            .then(function (serverData) {
                if (serverData && serverData.full_name) {
                    const localStored = localStorage.getItem('studenthub_profile');
                    let merged = serverData;
                    if (localStored) {
                        try {
                            const localData = JSON.parse(localStored);
                            // Merge keeping server data primary if logged in
                            merged = Object.assign({}, localData, serverData);
                        } catch (e) {}
                    }
                    localStorage.setItem('studenthub_profile', JSON.stringify(merged));
                    localStorage.setItem('studenthub_profile_name', merged.full_name);
                    applyProfileToPage(merged);
                }
            })
            .catch(function () {
                // Ignore API failure in offline or non-PHP environments
            });
    }

    // Run immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            loadFromLocalStorage();
            fetchLiveProfile();
        });
    } else {
        loadFromLocalStorage();
        fetchLiveProfile();
    }

    // Listen for storage events across tabs
    window.addEventListener('storage', function (e) {
        if (e.key === 'studenthub_profile' || e.key === 'studenthub_profile_name') {
            loadFromLocalStorage();
        }
    });

    // Expose global updater function
    window.updateStudentHubProfile = function (newProfile) {
        if (!newProfile) return;
        const current = loadFromLocalStorage() || {};
        const updated = Object.assign({}, current, newProfile);
        if (!updated.cgpa) updated.cgpa = DEFAULT_CGPA;
        localStorage.setItem('studenthub_profile', JSON.stringify(updated));
        if (updated.full_name) {
            localStorage.setItem('studenthub_profile_name', updated.full_name);
        }
        applyProfileToPage(updated);
    };
})();
