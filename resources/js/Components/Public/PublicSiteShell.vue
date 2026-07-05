<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { publicNavigation } from './publicPages';
/** Дублирует resources/locales/public/ru.json — если с сервера пришёл пустой publicSite.texts, витрина не остаётся без текста. */
import publicSiteRuFallbacks from './publicSiteRuFallbacks.json';

const props = defineProps({
    page: {
        type: Object,
        required: true,
    },
    canLogin: {
        type: Boolean,
        default: true,
    },
    canRegister: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const activeSection = ref(0);
const slaActivePanel = ref(null);
const slaDocumentPreviewUrl = ref(null);
const isLoginOpen = ref(false);
const isMobileMenuOpen = ref(false);
const isReady = ref(false);
const authLogoUrl = '/assets/logo_black.png?v=2';
const headerLogoUrl = '/assets/logo_white.png?v=2';
const companyPhoneLabel = '+7 8482 55 99 99';
const companyPhoneHref = 'tel:+78482559999';
const trakloApkUrl = computed(() => page.props.publicSite?.traklo_apk_url ?? '/downloads/traklo.apk');

const isNonEmptyTextMap = (value) =>
    value !== null && typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length > 0;

const texts = computed(() => {
    const fromServer = page.props.publicSite?.texts;
    if (isNonEmptyTextMap(fromServer)) {
        return fromServer;
    }

    return publicSiteRuFallbacks;
});

const activeLocale = computed(() => String(page.props.publicSite?.active_locale ?? 'ru').toLowerCase());

const availableLocales = computed(
    () =>
        page.props.publicSite?.available_locales ?? [
            { code: 'ru', label: 'RU' },
            { code: 'en', label: 'EN' },
            { code: 'cn', label: '中文' },
        ],
);

const localeSwitchHref = (code) => route('public.locale.switch', { locale: code });

const crmLoginUrl = computed(() => page.props.publicSite?.crm_login_url ?? '/login');

const checkoCompanyUrl = computed(() => {
    const u = page.props.publicSite?.checko_company_url;
    if (typeof u === 'string' && u.trim() !== '') {
        return u.trim();
    }

    return 'https://checko.ru/company/avtoalyans-smolensk-1156733014899';
});

const checkoWidgetTitle = 'ИНН 6732110940 — карточка компании на портале «Чекко»';
/** URL дашборда на том же хосте, что и страница входа в кабинет (для витрины ≠ CRM). */
const crmDashboardUrl = computed(() => {
    try {
        const u = new URL(crmLoginUrl.value);
        u.pathname = '/dashboard';
        u.search = '';
        u.hash = '';

        return u.toString();
    } catch {
        return '/dashboard';
    }
});
/** Inertia-навигация на другой origin ломается (CORS); нужна обычная ссылка. */
const isCrmAnotherOrigin = computed(() => {
    if (typeof window === 'undefined') {
        return false;
    }
    try {
        return new URL(crmLoginUrl.value).origin !== window.location.origin;
    } catch {
        return false;
    }
});
const authUser = computed(() => page.props.auth?.user ?? null);
const sections = computed(() => props.page.sections ?? []);
const isVerticalMode = computed(() => props.page.mode === 'vertical');
const isCasesMode = computed(() => props.page.mode === 'cases');
const isContactsMode = computed(() => props.page.mode === 'contacts');
const isSlaMode = computed(() => props.page.mode === 'sla');

const slaPanels = computed(() => props.page.panels ?? []);

const activeSlaPanel = computed(
    () => slaPanels.value.find((panel) => panel.id === slaActivePanel.value) ?? null,
);

const slaDocumentCatalog = computed(() => page.props.publicSite?.sla_documents ?? []);

const activeSlaPanelDocuments = computed(() =>
    slaDocumentCatalog.value.filter((document) => document.panel === slaActivePanel.value),
);

const slaIsDetailView = computed(() => slaActivePanel.value !== null);

const slaHubBackground = computed(() => props.page.media ?? '/assets/images/SLA_common.webp');

const slaDetailBackground = computed(
    () => activeSlaPanel.value?.media ?? slaHubBackground.value,
);

const openSlaPanel = (panelId) => {
    slaDocumentPreviewUrl.value = null;
    slaActivePanel.value = panelId;
};

const closeSlaPanel = () => {
    slaDocumentPreviewUrl.value = null;
    slaActivePanel.value = null;
};

const openSlaDocument = (document) => {
    if (!document?.preview_url) {
        return;
    }

    slaDocumentPreviewUrl.value = document.preview_url;
};

const closeSlaDocument = () => {
    slaDocumentPreviewUrl.value = null;
};

const loginForm = useForm('PublicSiteLoginModal', {
    email: '',
    password: '',
    remember: false,
}).dontRemember('password');

const t = (key, fallback = '') => {
    const raw = texts.value[key];
    if (raw !== undefined && raw !== null && String(raw).trim() !== '') {
        return raw;
    }

    return fallback;
};

const companyBrand = computed(() => t('site_brand', 'Автоальянс Смоленск'));
const companyEmailLabel = computed(() => t('footer_email', '').trim());
const companyEmailHref = computed(() => `mailto:${companyEmailLabel.value}`);

const interpolateCompany = (value) =>
    String(value ?? '')
        .replaceAll('[Название компании]', companyBrand.value)
        .replaceAll('{company_name}', companyBrand.value);

const tRich = (key) => interpolateCompany(t(key));

const switchSection = (index) => {
    if (!sections.value.length) {
        return;
    }

    if (index < 0) {
        activeSection.value = sections.value.length - 1;
        return;
    }

    if (index >= sections.value.length) {
        activeSection.value = 0;
        return;
    }

    activeSection.value = index;
};

const openLogin = () => {
    isMobileMenuOpen.value = false;
    window.location.assign(crmLoginUrl.value);
};

const closeLogin = () => {
    isLoginOpen.value = false;
    loginForm.reset('password');
};

const toggleMobileMenu = () => {
    isMobileMenuOpen.value = !isMobileMenuOpen.value;
};

const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

const submitLogin = () => {
    loginForm.post(route('login'), {
        preserveScroll: true,
        onSuccess: () => {
            closeLogin();
        },
        onFinish: () => {
            loginForm.reset('password');
        },
    });
};

const handleWheel = (event) => {
    if (!isVerticalMode.value || isLoginOpen.value || sections.value.length <= 1) {
        return;
    }

    const direction = Math.sign(event.deltaY);

    if (direction === 0) {
        return;
    }

    event.preventDefault();
    switchSection(activeSection.value + direction);
};

const handleKeydown = (event) => {
    if (event.key === 'Escape' && isLoginOpen.value) {
        closeLogin();
        return;
    }

    if (isLoginOpen.value) {
        return;
    }

    if (isVerticalMode.value && (event.key === 'ArrowDown' || event.key === 'PageDown')) {
        event.preventDefault();
        switchSection(activeSection.value + 1);
    }

    if (isVerticalMode.value && (event.key === 'ArrowUp' || event.key === 'PageUp')) {
        event.preventDefault();
        switchSection(activeSection.value - 1);
    }

    if (isCasesMode.value && event.key === 'ArrowRight') {
        event.preventDefault();
        switchSection(activeSection.value + 1);
    }

    if (isCasesMode.value && event.key === 'ArrowLeft') {
        event.preventDefault();
        switchSection(activeSection.value - 1);
    }
};

watch(
    () => page.props.errors,
    (errors) => {
        if (errors?.email || errors?.password) {
            isLoginOpen.value = true;
        }
    },
    { deep: true },
);

watch(
    () => props.page.pageKey,
    () => {
        activeSection.value = 0;
        slaActivePanel.value = null;
        slaDocumentPreviewUrl.value = null;
    },
);

watch([isLoginOpen, isMobileMenuOpen], ([loginOpen, mobileMenuOpen]) => {
    document.body.style.overflow = loginOpen || mobileMenuOpen ? 'hidden' : '';
});

onMounted(() => {
    isReady.value = true;
    window.addEventListener('wheel', handleWheel, { passive: false });
    window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('wheel', handleWheel);
    window.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="public-site">
        <Head :title="page.title" />

        <header class="header">
            <div class="header-container">
                <Link href="/" class="site-title-link">
                    <img
                        :src="headerLogoUrl"
                        alt="Автоальянс Смоленск"
                        class="site-title-logo"
                    >
                </Link>

                <nav class="nav">
                    <Link
                        v-for="item in publicNavigation"
                        :key="item.key"
                        :href="item.href"
                        :class="{ active: item.key === page.pageKey }"
                    >
                        {{ t(item.labelKey, item.key) }}
                    </Link>

                    <a
                        v-if="authUser && isCrmAnotherOrigin"
                        :href="crmDashboardUrl"
                        class="login-nav-link"
                    >
                        В кабинет
                    </a>
                    <Link
                        v-else-if="authUser"
                        :href="route('dashboard')"
                        class="login-nav-link"
                    >
                        В кабинет
                    </Link>

                    <a
                        v-else
                        :href="crmLoginUrl"
                        class="login-nav-link"
                    >
                        {{ t('cabinet', 'Личный кабинет') }}
                    </a>
                </nav>

                <button
                    type="button"
                    class="mobile-nav-toggle"
                    :aria-expanded="isMobileMenuOpen"
                    aria-label="Открыть меню"
                    @click="toggleMobileMenu"
                >
                    <span />
                    <span />
                    <span />
                </button>
            </div>
        </header>

        <div
            v-if="isMobileMenuOpen"
            class="mobile-nav-overlay"
            @click="closeMobileMenu"
        />

        <div class="mobile-nav-panel" :class="{ active: isMobileMenuOpen }">
            <nav class="mobile-nav-list">
                <Link
                    v-for="item in publicNavigation"
                    :key="`mobile-${item.key}`"
                    :href="item.href"
                    :class="{ active: item.key === page.pageKey }"
                    @click="closeMobileMenu"
                >
                    {{ t(item.labelKey, item.key) }}
                </Link>

                <a
                    v-if="authUser && isCrmAnotherOrigin"
                    :href="crmDashboardUrl"
                    class="mobile-nav-login"
                    @click="closeMobileMenu"
                >
                    В кабинет
                </a>
                <Link
                    v-else-if="authUser"
                    :href="route('dashboard')"
                    class="mobile-nav-login"
                    @click="closeMobileMenu"
                >
                    В кабинет
                </Link>

                <a
                    v-else
                    :href="crmLoginUrl"
                    class="mobile-nav-login"
                    @click="closeMobileMenu"
                >
                    {{ t('cabinet', 'Личный кабинет') }}
                </a>

            </nav>
        </div>

        <main v-if="isVerticalMode" class="fullpage-carousel">
            <section
                v-for="(section, index) in sections"
                :key="section.id"
                class="carousel-section"
                :class="{ active: index === activeSection }"
            >
                <div class="overlay" />

                <video
                    v-if="section.mediaType === 'video'"
                    autoplay
                    muted
                    loop
                    playsinline
                    class="bg-video"
                >
                    <source :src="section.media" type="video/mp4">
                    <span>{{ t('not_video', 'Ваш браузер не поддерживает видео.') }}</span>
                </video>

                <div
                    v-else
                    class="bg-image"
                    :style="{ backgroundImage: `url('${section.media}')` }"
                />

                <div class="section-content" :class="{ 'section-content--rich': section.richText }">
                    <p v-if="section.eyebrowKey" class="section-eyebrow">
                        {{ t(section.eyebrowKey) }}
                    </p>
                    <h1 v-if="index === 0">{{ t(section.titleKey) }}</h1>
                    <h2 v-else>{{ t(section.titleKey) }}</h2>
                    <div
                        v-if="section.richText"
                        class="section-prose"
                        v-html="tRich(section.textKey)"
                    />
                    <p v-else>{{ t(section.textKey) }}</p>
                </div>
            </section>
        </main>

        <main v-else-if="isSlaMode" class="sla-page">
            <div class="sla-bg-stack" aria-hidden="true">
                <div
                    class="sla-bg bg-image"
                    :class="{ 'is-visible': !slaIsDetailView }"
                    :style="{ backgroundImage: `url('${slaHubBackground}')` }"
                />
                <div
                    class="sla-bg bg-image sla-bg--layer"
                    :class="{ 'is-visible': slaIsDetailView }"
                    :style="{ backgroundImage: `url('${slaDetailBackground}')` }"
                />
            </div>
            <div class="overlay" />

            <div class="sla-stage">
                <div class="sla-track" :class="{ 'is-detail': slaIsDetailView }">
                    <div class="sla-panel sla-panel--hub" :class="{ 'is-slid-out': slaIsDetailView }">
                        <div class="sla-container">
                            <div class="sla-content">
                                <h1>{{ t(page.introTitleKey ?? 'sla_intro_title') }}</h1>
                                <div
                                    class="section-prose sla-prose"
                                    v-html="tRich(page.introTextKey ?? 'sla_intro_text')"
                                />
                            </div>

                            <div class="sla-divider" aria-hidden="true" />

                            <div class="sla-tiles">
                                <button
                                    v-for="panel in slaPanels"
                                    :key="panel.id"
                                    type="button"
                                    class="sla-tile"
                                    @click="openSlaPanel(panel.id)"
                                >
                                    {{ t(panel.labelKey) }}
                                </button>

                                <a
                                    :href="trakloApkUrl"
                                    class="sla-tile sla-tile--doc"
                                    download="Traklo.apk"
                                >
                                    {{ t('sla_tile_traklo_download', 'Скачать приложение') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="sla-panel sla-panel--detail" :class="{ 'is-active': slaIsDetailView }">
                        <div v-if="activeSlaPanel" class="sla-container sla-container--enter">
                            <div class="sla-content">
                                <h1>{{ t(activeSlaPanel.titleKey) }}</h1>
                                <div
                                    class="section-prose sla-prose"
                                    v-html="tRich(activeSlaPanel.textKey)"
                                />
                            </div>

                            <div class="sla-divider" aria-hidden="true" />

                            <div class="sla-tiles">
                                <button
                                    type="button"
                                    class="sla-tile sla-tile--back"
                                    @click="closeSlaPanel"
                                >
                                    {{ t('sla_tile_back', 'Назад') }}
                                </button>

                                <button
                                    v-for="document in activeSlaPanelDocuments"
                                    :key="document.id"
                                    type="button"
                                    class="sla-tile sla-tile--doc"
                                    @click="openSlaDocument(document)"
                                >
                                    {{ document.label }}
                                </button>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="slaDocumentPreviewUrl"
                class="sla-doc-overlay"
                role="dialog"
                aria-modal="true"
                :aria-label="t('sla_doc_preview_title', 'Предпросмотр документа')"
                @click.self="closeSlaDocument"
            >
                <div class="sla-doc-dialog">
                    <div class="sla-doc-toolbar">
                        <span>{{ t('sla_doc_preview_title', 'Предпросмотр документа') }}</span>
                        <button type="button" class="sla-doc-close" @click="closeSlaDocument">
                            {{ t('sla_doc_close', 'Закрыть') }}
                        </button>
                    </div>
                    <iframe
                        :src="slaDocumentPreviewUrl"
                        class="sla-doc-frame"
                        title="SLA document preview"
                    />
                </div>
            </div>
        </main>

        <main v-else-if="isCasesMode" class="cases-page">
            <div class="cases-backgrounds">
                <div class="overlay" />
                <div
                    v-for="(section, index) in sections"
                    :key="section.id"
                    class="case-bg"
                    :class="{ active: index === activeSection }"
                    :style="{ backgroundImage: `url('${section.media}')` }"
                />
            </div>

            <div class="cases-container">
                <section class="case-section">
                    <div class="case-content">
                        <div class="text-container active">
                            <h2>{{ t(sections[activeSection].titleKey) }}</h2>
                            <p>{{ t(sections[activeSection].textKey) }}</p>

                            <div class="case-stats">
                                <div
                                    v-for="stat in sections[activeSection].stats"
                                    :key="`${sections[activeSection].id}-${stat.labelKey}`"
                                    class="stat"
                                >
                                    <span class="stat-value">{{ stat.value }}</span>
                                    <span class="stat-label">{{ t(stat.labelKey) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>

        <main v-else-if="isContactsMode" class="contacts-page">
            <div class="contacts-container">
                <div class="contacts-info">
                    <h1>{{ t('our_contacts') }}</h1>

                    <div class="contact-item">
                        <h3>{{ t('adress_title') }}</h3>
                        <p>{{ t('adress') }}</p>
                    </div>

                    <div class="contact-item">
                        <h3>{{ t('email') }}</h3>
                        <p><a :href="companyEmailHref">{{ companyEmailLabel }}</a></p>
                    </div>

                    <div class="contact-item">
                        <h3>{{ t('phone', 'Телефон') }}</h3>
                        <p><a :href="companyPhoneHref">{{ companyPhoneLabel }}</a></p>
                    </div>

                    <div class="contact-item">
                        <h3>{{ t('work_hours') }}</h3>
                        <p>{{ t('weekdays') }}</p>
                    </div>
                </div>

                <div class="contacts-map">
                    <iframe
                        src="https://yandex.ru/map-widget/v1/?um=constructor%3A1a2b3c4d5e6f7g8h9i0j&amp;source=constructor"
                        width="100%"
                        height="400"
                        frameborder="0"
                        title="Карта"
                    />
                </div>
            </div>
        </main>

        <div v-if="isVerticalMode" class="section-nav">
            <button class="scroll-btn scroll-up" type="button" @click="switchSection(activeSection - 1)">
                <span class="arrow arrow-up" />
            </button>

            <div class="nav-dots">
                <button
                    v-for="(section, index) in sections"
                    :key="section.id"
                    type="button"
                    class="nav-dot"
                    :class="{ active: index === activeSection }"
                    @click="switchSection(index)"
                />
            </div>

            <button class="scroll-btn scroll-down" type="button" @click="switchSection(activeSection + 1)">
                <span class="arrow arrow-down" />
            </button>
        </div>

        <div v-if="isCasesMode" class="cases-navigation">
            <button class="nav-arrow prev" type="button" aria-label="Предыдущий кейс" @click="switchSection(activeSection - 1)">
                <span>&#10094;</span>
            </button>

            <button class="nav-arrow next" type="button" aria-label="Следующий кейс" @click="switchSection(activeSection + 1)">
                <span>&#10095;</span>
            </button>
        </div>

        <div v-if="isCasesMode" class="cases-dots-container">
            <div class="cases-dots">
                <button
                    v-for="(section, index) in sections"
                    :key="section.id"
                    type="button"
                    class="dot"
                    :class="{ active: index === activeSection }"
                    @click="switchSection(index)"
                />
            </div>
        </div>

        <footer class="footer">
            <div class="footer-content">
                <div class="footer-info">
                    <p>{{ t('footer_name') }}</p>
                    <p>{{ t('footer_address') }}</p>
                    <p><a :href="companyEmailHref">{{ companyEmailLabel }}</a></p>
                    <p><a :href="companyPhoneHref">{{ companyPhoneLabel }}</a></p>
                </div>

                <div class="footer-end">
                    <a
                        class="footer-checko"
                        :href="checkoCompanyUrl"
                        :title="checkoWidgetTitle"
                        rel="noopener noreferrer"
                    >
                        <img width="150" height="50" src="https://checko.ru/cdn/widget/300x100_white.png" alt="Checko">
                    </a>

                    <nav class="footer-lang" aria-label="Язык интерфейса">
                        <a
                            v-for="loc in availableLocales"
                            :key="loc.code"
                            class="footer-lang-link"
                            :class="{ active: loc.code === activeLocale }"
                            :href="localeSwitchHref(loc.code)"
                        >
                            {{ loc.label }}
                        </a>
                    </nav>
                </div>
            </div>
        </footer>

        <div v-if="isReady" class="auth-modal" :class="{ active: isLoginOpen }">
            <div class="auth-modal-overlay" @click="closeLogin" />

            <div class="auth-modal-container">
                <button class="auth-modal-close" type="button" @click="closeLogin">
                    ×
                </button>

                <div class="auth-modal-content">
                    <div class="auth-form active">
                        <a :href="route('public.home')" class="auth-brand">
                            <img
                                :src="authLogoUrl"
                                alt=""
                                class="h-auto w-full object-contain"
                            >
                        </a>
                        <h3 class="auth-title">Добро пожаловать</h3>
                        <p class="auth-subtitle">Войдите в свой аккаунт</p>

                        <form @submit.prevent="submitLogin">
                            <div class="form-group">
                                <label for="loginEmail">Email</label>
                                <input
                                    id="loginEmail"
                                    v-model="loginForm.email"
                                    type="email"
                                    name="email"
                                    placeholder="your@email.com"
                                    required
                                    autocomplete="username"
                                >
                                <InputError class="form-error" :message="loginForm.errors.email" />
                            </div>

                            <div class="form-group">
                                <label for="loginPassword">Пароль</label>
                                <input
                                    id="loginPassword"
                                    v-model="loginForm.password"
                                    type="password"
                                    name="password"
                                    placeholder="••••••••"
                                    required
                                    autocomplete="current-password"
                                >
                                <InputError class="form-error" :message="loginForm.errors.password" />
                            </div>

                            <div class="form-options">
                                <label class="checkbox-label">
                                    <input v-model="loginForm.remember" type="checkbox" name="remember">
                                    <span>Запомнить меня</span>
                                </label>
                            </div>

                            <button type="submit" class="auth-submit-btn" :disabled="loginForm.processing">
                                {{ loginForm.processing ? 'Вход...' : 'Войти' }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="auth-decoration" />
            </div>
        </div>
    </div>
</template>

<style scoped>
* {
  box-sizing: border-box;
}

.public-site {
  font-family: Arial, sans-serif;
  background-color: #1a1a1a;
  color: #f5f5f5;
  overflow-x: hidden;
  font-size: 16px;
  line-height: 1.6;
}

.header {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  padding: 15px 20px;
  background-color: rgba(17, 17, 17, 0.95);
  z-index: 1000;
  display: flex;
  justify-content: space-between;
  align-items: center;
  backdrop-filter: blur(10px);
}

.header-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  max-width: 1400px;
  margin: 0 auto;
}

.site-title-link {
  flex-shrink: 0;
  text-decoration: none;
  display: block;
}

.site-title-logo {
  display: block;
  height: 2.5rem;
  width: auto;
  max-width: min(320px, 55vw);
  object-fit: contain;
}

.site-title-link:hover .site-title-logo {
  opacity: 0.88;
}

.nav {
  display: flex;
  flex-shrink: 0;
  align-items: center;
  flex-wrap: wrap;
  row-gap: 4px;
}

.mobile-nav-toggle {
  flex-shrink: 0;
}

.mobile-nav-toggle,
.mobile-nav-panel,
.mobile-nav-overlay {
  display: none;
}

.nav :deep(a) {
  color: #f5f5f5;
  text-decoration: none;
  margin-left: 25px;
  font-weight: 500;
  transition: color 0.3s;
  position: relative;
  padding: 5px 0;
}

.nav :deep(a:hover) {
  color: #ff5722;
}

.nav :deep(a::after) {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 2px;
  background: #ff5722;
  transition: width 0.3s ease;
}

.nav :deep(a:hover::after),
.nav :deep(a.active::after) {
  width: 100%;
}

.nav :deep(a.active) {
  color: #ff5722;
}

.login-nav-link {
  background: linear-gradient(45deg, #ff5722, #ff9800);
  color: white !important;
  padding: 8px 20px !important;
  border-radius: 25px;
  margin-left: 15px;
  font-weight: 600;
  transition: all 0.3s ease !important;
}

.login-nav-link:hover {
  background: linear-gradient(45deg, #ff9800, #ff5722);
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(255, 87, 34, 0.3);
}

.login-nav-link::after {
  display: none;
}

.fullpage-carousel {
  position: relative;
  height: 100vh;
  width: 100%;
  overflow: hidden;
}

.carousel-section {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  transform: translateY(100%);
  transition: transform 1s cubic-bezier(0.77, 0, 0.175, 1), opacity 0.5s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.carousel-section.active {
  opacity: 1;
  transform: translateY(0);
  z-index: 10;
}

.bg-video,
.bg-image {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: -1;
}

.bg-image {
  background-size: cover;
  background-position: center;
  background-color: #2a2a2a;
}

.overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.55);
  z-index: 0;
}

.section-content {
  position: relative;
  z-index: 2;
  color: white;
  max-width: 800px;
  padding: 0 20px;
  text-align: center;
}

.section-content h1 {
  font-size: 2.8rem;
  margin-bottom: 20px;
  font-weight: 700;
  animation: fadeInUp 0.8s ease;
}

.section-content h2 {
  font-size: 2.4rem;
  margin-bottom: 20px;
  font-weight: 700;
  animation: fadeInUp 0.8s ease 0.2s both;
}

.section-content p {
  font-size: 1.2rem;
  color: #ddd;
  margin-bottom: 30px;
  line-height: 1.8;
  animation: fadeInUp 0.8s ease 0.4s both;
}

.section-content--rich {
  max-width: 920px;
  max-height: calc(100vh - 160px);
  overflow-y: auto;
  text-align: left;
  padding-right: 6px;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.35) transparent;
}

.section-eyebrow {
  margin: 0 0 10px;
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #f0c040;
  animation: fadeInUp 0.8s ease 0.1s both;
}

.section-prose {
  font-size: 1.05rem;
  line-height: 1.75;
  color: #e8e8e8;
  animation: fadeInUp 0.8s ease 0.4s both;
}

.section-prose :deep(p) {
  margin: 0 0 1rem;
}

.section-prose :deep(p:last-child) {
  margin-bottom: 0;
}

.section-prose :deep(h3) {
  margin: 1.25rem 0 0.75rem;
  font-size: 1.15rem;
  font-weight: 700;
  color: #fff;
}

.section-prose :deep(ul) {
  margin: 0 0 1rem;
  padding-left: 1.25rem;
  list-style: disc;
}

.section-prose :deep(li) {
  margin-bottom: 0.65rem;
}

.section-prose :deep(li:last-child) {
  margin-bottom: 0;
}

.section-prose :deep(strong) {
  color: #fff;
  font-weight: 600;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.section-nav {
  position: fixed;
  right: 30px;
  top: 50%;
  transform: translateY(-50%);
  z-index: 100;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 15px;
  background: rgba(17, 17, 17, 0.4);
  border-radius: 30px;
  padding: 15px 10px;
  backdrop-filter: blur(10px);
}

.nav-dots {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.nav-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.5);
  border: none;
  cursor: pointer;
  transition: all 0.3s ease;
}

.nav-dot.active {
  background: #ff5722;
  transform: scale(1.3);
  box-shadow: 0 0 10px rgba(255, 87, 34, 0.5);
}

.nav-dot:hover {
  background: rgba(255, 87, 34, 0.7);
}

.scroll-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
}

.scroll-btn:hover {
  background: rgba(255, 87, 34, 0.2);
  border-color: #ff5722;
}

.scroll-btn .arrow {
  display: block;
  width: 15px;
  height: 15px;
  border-bottom: 2px solid #fff;
  border-right: 2px solid #fff;
  transition: border-color 0.3s;
}

.scroll-btn:hover .arrow {
  border-color: #ff5722;
}

.arrow-up {
  transform: rotate(-135deg) translate(-2px, -2px);
}

.arrow-down {
  transform: rotate(45deg) translate(-2px, -2px);
}

.sla-page {
  position: relative;
  min-height: 100vh;
  padding: 100px 0 90px;
  overflow: hidden;
}

.sla-bg-stack {
  position: absolute;
  inset: 0;
  z-index: 0;
  overflow: hidden;
}

.sla-bg {
  position: absolute;
  inset: 0;
  opacity: 0;
  transform: scale(1.04);
  transition:
    opacity 0.65s ease,
    transform 0.85s ease;
}

.sla-bg.is-visible {
  opacity: 1;
  transform: scale(1);
}

.sla-page > .overlay {
  z-index: 1;
}

.sla-stage {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 1060px;
  margin: 0 auto;
  padding: 0 20px;
  overflow: hidden;
  isolation: isolate;
}

.sla-track {
  display: flex;
  width: 200%;
  transition: transform 0.55s cubic-bezier(0.77, 0, 0.175, 1);
}

.sla-track.is-detail {
  transform: translateX(-50%);
}

.sla-panel {
  width: 50%;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: calc(100vh - 200px);
  overflow: hidden;
  contain: paint;
}

.sla-panel--hub.is-slid-out .sla-tiles,
.sla-panel--hub.is-slid-out .sla-divider {
  opacity: 0;
  visibility: hidden;
  transform: translateX(20px);
  transition:
    opacity 0.3s ease,
    transform 0.45s ease,
    visibility 0s linear 0.45s;
}

.sla-panel--hub.is-slid-out .sla-content {
  opacity: 0.35;
  transition: opacity 0.4s ease;
}

.sla-panel--detail.is-active .sla-container--enter {
  animation: slaDetailEnter 0.55s cubic-bezier(0.77, 0, 0.175, 1) both;
}

@keyframes slaDetailEnter {
  from {
    opacity: 0;
    transform: translateX(32px);
  }

  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.sla-container {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 32px;
  width: 100%;
}

.sla-content {
  flex: 1 1 auto;
  width: 100%;
  max-width: 800px;
  color: #fff;
  text-align: center;
  max-height: calc(100vh - 200px);
  overflow-y: auto;
  padding: 0 8px;
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.35) transparent;
}

.sla-content h1 {
  margin: 0 0 20px;
  font-size: 2.8rem;
  font-weight: 700;
  line-height: 1.2;
  animation: fadeInUp 0.8s ease;
}

.sla-prose {
  font-size: 1.2rem;
  line-height: 1.8;
  color: #ddd;
  text-align: left;
  animation: fadeInUp 0.8s ease 0.2s both;
}

.sla-divider {
  flex-shrink: 0;
  width: 1px;
  align-self: center;
  min-height: 9rem;
  background: linear-gradient(
    to bottom,
    rgba(255, 255, 255, 0),
    rgba(255, 255, 255, 0.45) 15%,
    rgba(255, 255, 255, 0.45) 85%,
    rgba(255, 255, 255, 0)
  );
}

.sla-tiles {
  display: flex;
  flex-shrink: 0;
  flex-direction: column;
  justify-content: center;
  gap: 12px;
  width: 11.5rem;
}

.sla-tile {
  width: 100%;
  min-height: 4.25rem;
  padding: 0.9rem 1rem;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 12px;
  background: rgba(18, 18, 18, 0.52);
  color: #fff;
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.35;
  text-align: center;
  cursor: pointer;
  transition:
    background 0.25s ease,
    border-color 0.25s ease,
    transform 0.25s ease,
    opacity 0.3s ease,
    visibility 0.3s ease;
}

.sla-tile:hover {
  background: rgba(30, 30, 30, 0.62);
  border-color: rgba(255, 255, 255, 0.45);
  transform: translateY(-1px);
}

.sla-tile--back {
  background: rgba(0, 0, 0, 0.28);
}

.sla-tile--doc {
  background: rgba(255, 255, 255, 0.14);
}

a.sla-tile {
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}

.sla-doc-overlay {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: rgba(0, 0, 0, 0.72);
}

.sla-doc-dialog {
  display: flex;
  flex-direction: column;
  width: min(960px, 100%);
  height: min(85vh, 900px);
  border-radius: 12px;
  overflow: hidden;
  background: #1a1a1a;
  border: 1px solid rgba(255, 255, 255, 0.15);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.45);
}

.sla-doc-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 14px;
  color: #fff;
  font-size: 0.9rem;
  background: rgba(255, 255, 255, 0.06);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sla-doc-close {
  border: 0;
  border-radius: 8px;
  padding: 6px 12px;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  cursor: pointer;
}

.sla-doc-close:hover {
  background: rgba(255, 255, 255, 0.2);
}

.sla-doc-frame {
  flex: 1;
  width: 100%;
  border: 0;
  background: #fff;
}

.contacts-page {
  min-height: 100vh;
  padding-top: 100px;
  padding-bottom: 100px;
}

.contacts-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40px;
}

.contact-item {
  margin-bottom: 30px;
}

.contact-item h3 {
  color: #ff5722;
  margin-bottom: 10px;
  font-size: 1.4rem;
}

.contact-item p {
  font-size: 1.1rem;
  line-height: 1.6;
  color: #ddd;
}

.contact-item a {
  color: inherit;
  text-decoration: none;
}

.contact-item a:hover {
  color: #ff8c5a;
}

.contacts-map {
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

.cases-page {
  min-height: 100vh;
}

.cases-backgrounds {
  position: fixed;
  inset: 0;
  z-index: 1;
  overflow: hidden;
}

.case-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  opacity: 0;
  transition: opacity 1.8s cubic-bezier(0.16, 1, 0.3, 1);
}

.case-bg.active {
  opacity: 1;
}

.cases-container {
  position: relative;
  width: 100%;
  height: 100vh;
  overflow: hidden;
  z-index: 2;
}

.case-section {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.case-content {
  position: relative;
  z-index: 3;
  width: 100%;
  max-width: 1200px;
  padding: 0 40px;
}

.text-container {
  color: white;
  max-width: 600px;
  position: relative;
}

.text-container h2 {
  font-size: 2.5rem;
  margin-bottom: 20px;
  color: #ff5722;
}

.text-container p {
  font-size: 1.2rem;
  line-height: 1.6;
  margin-bottom: 30px;
}

.case-stats {
  display: flex;
  gap: 30px;
  margin-top: 30px;
}

.stat {
  text-align: center;
}

.stat-value {
  display: block;
  font-size: 1.8rem;
  font-weight: bold;
  color: #ff5722;
  margin-bottom: 5px;
}

.stat-label {
  font-size: 0.9rem;
  color: #ccc;
}

.cases-navigation {
  position: fixed;
  top: 50%;
  left: 0;
  right: 0;
  transform: translateY(-50%);
  display: flex;
  justify-content: space-between;
  padding: 0 20px;
  z-index: 100;
  pointer-events: none;
}

.nav-arrow {
  background: rgba(42, 42, 42, 0.8);
  border: 2px solid #ff5722;
  color: white;
  width: 60px;
  height: 60px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
  transition: all 0.3s ease;
  opacity: 0.7;
  pointer-events: auto;
}

.nav-arrow:hover {
  background: #ff5722;
  opacity: 1;
  transform: scale(1.1);
}

.cases-dots-container {
  position: fixed;
  bottom: 120px;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  z-index: 10;
}

.cases-dots {
  display: flex;
  gap: 15px;
  background: rgba(42, 42, 42, 0.6);
  padding: 10px 20px;
  border-radius: 30px;
  backdrop-filter: blur(10px);
}

.dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.4);
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
}

.dot:hover {
  transform: scale(1.3);
  background: rgba(255, 255, 255, 0.8);
}

.dot.active {
  background: #ff5722;
  transform: scale(1.4);
}

.footer {
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  background: rgba(17, 17, 17, 0.95);
  z-index: 90;
  padding: 15px 0;
  backdrop-filter: blur(10px);
}

.footer-content {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 20px;
  gap: 16px;
}

.footer-end {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: flex-end;
  gap: 14px;
  flex-shrink: 0;
}

.footer-checko {
  display: inline-flex;
  flex-shrink: 0;
  line-height: 0;
}

.footer-checko img {
  display: block;
  max-width: 100%;
  height: auto;
}

.footer-lang {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
}

.footer-lang-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 40px;
  padding: 6px 10px;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  color: #ccc;
  text-decoration: none;
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: rgba(255, 255, 255, 0.04);
  transition: color 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
}

.footer-lang-link:hover {
  color: #ff8c5a;
  border-color: rgba(255, 87, 34, 0.45);
  background: rgba(255, 87, 34, 0.1);
}

.footer-lang-link.active {
  color: #ff5722;
  border-color: rgba(255, 87, 34, 0.55);
  background: rgba(255, 87, 34, 0.16);
}

.footer-info p {
  margin: 5px 0;
  font-size: 0.9rem;
  color: #aaa;
}

.footer-info a {
  color: inherit;
  text-decoration: none;
}

.footer-info a:hover {
  color: #ff8c5a;
}

.auth-modal {
  position: fixed;
  inset: 0;
  z-index: 9999;
  visibility: hidden;
  opacity: 0;
  transition: visibility 0.3s ease, opacity 0.3s ease;
}

.auth-modal.active {
  visibility: visible;
  opacity: 1;
}

.auth-modal-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  cursor: pointer;
}

.auth-modal-container {
  position: absolute;
  right: 0;
  top: 0;
  width: 25%;
  min-width: 320px;
  height: 100vh;
  background: linear-gradient(180deg, rgba(255, 252, 247, 0.88) 0%, rgba(248, 239, 231, 0.97) 100%);
  border-left: 1px solid rgba(106, 72, 42, 0.14);
  box-shadow: -16px 0 48px rgba(0, 0, 0, 0.28);
  transform: translateX(100%);
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  overflow-y: auto;
  overflow-x: hidden;
  z-index: 10000;
  backdrop-filter: blur(18px);
}

.auth-modal.active .auth-modal-container {
  transform: translateX(0);
}

.auth-modal-close {
  position: absolute;
  top: 20px;
  right: 20px;
  width: 36px;
  height: 36px;
  background: rgba(88, 59, 36, 0.08);
  border: none;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #4b321e;
  font-size: 22px;
  transition: all 0.3s ease;
  z-index: 10;
}

.auth-modal-close:hover {
  background: rgba(186, 129, 84, 0.16);
  transform: rotate(90deg);
}

.auth-modal-content {
  padding: 42px 32px 36px;
  min-height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.auth-brand {
  display: block;
  margin-bottom: 28px;
}

.auth-brand-title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 700;
  color: #241912;
  letter-spacing: 0.02em;
  line-height: 1.25;
}

.auth-form.active {
  display: block;
}

.auth-title {
  font-size: 28px;
  font-weight: 600;
  color: #241912;
  margin-bottom: 10px;
  letter-spacing: 0.01em;
}

.auth-subtitle {
  color: rgba(60, 41, 28, 0.66);
  font-size: 14px;
  margin-bottom: 30px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  color: rgba(46, 31, 20, 0.78);
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 8px;
}

.form-group input {
  width: 100%;
  padding: 12px 15px;
  background: rgba(255, 253, 249, 0.94);
  border: 1px solid rgba(128, 94, 64, 0.18);
  border-radius: 12px;
  color: #241912;
  font-size: 14px;
  transition: all 0.3s ease;
}

.form-group input:focus {
  outline: none;
  border-color: #cb966d;
  background: #fffdf9;
  box-shadow: 0 0 0 3px rgba(203, 150, 109, 0.16);
}

.form-group input::placeholder {
  color: rgba(95, 68, 46, 0.42);
}

.form-error {
  margin-top: 8px;
  color: #b14e2f;
  font-size: 12px;
}

.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
  font-size: 13px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  color: rgba(60, 41, 28, 0.74);
  cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
  width: 16px;
  height: 16px;
  cursor: pointer;
  accent-color: #c58f66;
}

.auth-submit-btn {
  width: 100%;
  padding: 14px;
  background: linear-gradient(135deg, #c79267 0%, #b47a53 100%);
  border: none;
  border-radius: 12px;
  color: #fffaf4;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 12px 22px rgba(140, 90, 51, 0.18);
}

.auth-submit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 26px rgba(140, 90, 51, 0.24);
}

.auth-submit-btn:disabled {
  opacity: 0.6;
  cursor: default;
  transform: none;
}

.auth-decoration {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 4px;
  background: linear-gradient(90deg, #cf9f77, #b77d58, #d8b08a);
  background-size: 200% 100%;
  animation: gradientMove 3s ease infinite;
}

@keyframes gradientMove {
  0%, 100% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
}

@media (max-width: 1200px) {
  .auth-modal-container {
    width: 33.333%;
  }
}

@media (max-width: 992px) {
  .nav {
    display: none;
  }

  .mobile-nav-toggle {
    width: 48px;
    height: 48px;
    display: inline-flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
    padding: 0 12px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 16px;
    cursor: pointer;
  }

  .mobile-nav-toggle span {
    width: 100%;
    height: 2px;
    border-radius: 999px;
    background: #f5f5f5;
  }

  .mobile-nav-overlay {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 995;
    background: rgba(0, 0, 0, 0.55);
    backdrop-filter: blur(6px);
  }

  .mobile-nav-panel {
    display: block;
    position: fixed;
    top: 88px;
    right: 20px;
    left: 20px;
    z-index: 998;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 24px;
    background: rgba(17, 17, 17, 0.96);
    box-shadow: 0 24px 48px rgba(0, 0, 0, 0.32);
    backdrop-filter: blur(18px);
    opacity: 0;
    pointer-events: none;
    transform: translateY(-12px);
    transition: opacity 0.25s ease, transform 0.25s ease;
  }

  .mobile-nav-panel.active {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
  }

  .mobile-nav-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 18px;
  }

  .mobile-nav-list :deep(a),
  .mobile-nav-login {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 12px 16px;
    border-radius: 16px;
    color: #f5f5f5;
    text-decoration: none;
    font-size: 15px;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid transparent;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
  }

  .mobile-nav-list :deep(a.active) {
    color: #ff8c5a;
    border-color: rgba(255, 87, 34, 0.35);
    background: rgba(255, 87, 34, 0.12);
  }

  .mobile-nav-login {
    border: none;
    cursor: pointer;
    background: linear-gradient(45deg, #ff5722, #ff9800);
    color: white;
  }
}

@media (max-width: 768px) {
  .auth-modal-container {
    width: 100%;
    min-width: auto;
  }

  .section-nav {
    background: rgba(17, 17, 17, 0.7);
    padding: 8px 6px;
    gap: 8px;
  }

  .scroll-btn {
    width: 30px;
    height: 30px;
  }

  .nav-dot {
    width: 10px;
    height: 10px;
  }

  .scroll-btn .arrow {
    width: 12px;
    height: 12px;
  }

  .contacts-container {
    grid-template-columns: 1fr;
  }

  .sla-page {
    padding: 96px 0 88px;
  }

  .sla-stage {
    padding: 0 16px;
  }

  .sla-panel {
    min-height: auto;
  }

  .sla-container {
    flex-direction: column;
    gap: 24px;
  }

  .sla-content {
    max-height: none;
  }

  .sla-content h1 {
    font-size: 2rem;
  }

  .sla-prose {
    font-size: 1.05rem;
  }

  .sla-divider {
    width: 100%;
    min-height: 0;
    height: 1px;
    background: linear-gradient(
      to right,
      rgba(255, 255, 255, 0),
      rgba(255, 255, 255, 0.45) 15%,
      rgba(255, 255, 255, 0.45) 85%,
      rgba(255, 255, 255, 0)
    );
  }

  .sla-tiles {
    flex-direction: row;
    width: 100%;
    max-width: 24rem;
  }

  .sla-tile {
    flex: 1;
    min-height: 3.75rem;
    padding: 0.75rem 0.65rem;
    font-size: 0.85rem;
  }

  .footer {
    padding: 10px 12px;
  }

  .footer .footer-info {
    display: none;
  }

  .footer .footer-content {
    justify-content: flex-end;
    padding: 0 8px;
  }

  .footer .footer-end {
    flex-direction: row;
    align-items: center;
    gap: 10px;
  }

  .footer .footer-checko img {
    width: 120px;
    height: auto;
  }
}
</style>
