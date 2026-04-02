export const publicPages = {
    home: {
        pageKey: 'home',
        title: 'Логистические решения',
        mode: 'vertical',
        sections: [
            {
                id: 'section1',
                titleKey: 'welcome_title',
                textKey: 'welcome_text',
                mediaType: 'video',
                media: '/assets/videos/bg-video.mp4',
            },
            {
                id: 'section2',
                titleKey: 'logistic',
                textKey: 'logistic_text',
                mediaType: 'image',
                media: '/assets/images/logistics-bg.jpg',
            },
            {
                id: 'section3',
                titleKey: 'expertise',
                textKey: 'expertise_text',
                mediaType: 'image',
                media: '/assets/images/expertise-bg.jpg',
            },
        ],
    },
    about: {
        pageKey: 'about',
        title: 'О компании',
        mode: 'vertical',
        sections: [
            {
                id: 'section1',
                titleKey: 'priority',
                textKey: 'priority_text',
                mediaType: 'image',
                media: '/assets/images/priority-bg.jpg',
            },
            {
                id: 'section2',
                titleKey: 'global_presence',
                textKey: 'global_presence_text',
                mediaType: 'image',
                media: '/assets/images/presense.jpg',
            },
            {
                id: 'section3',
                titleKey: 'partnership',
                textKey: 'partnership_text',
                mediaType: 'image',
                media: '/assets/images/partnership.jpg',
            },
        ],
    },
    services: {
        pageKey: 'services',
        title: 'Услуги',
        mode: 'vertical',
        sections: [
            {
                id: 'section1',
                titleKey: 'auto_delivery',
                textKey: 'auto_delivery_text',
                mediaType: 'image',
                media: '/assets/images/auto-bg.jpg',
            },
            {
                id: 'section2',
                titleKey: 'rail_delivery',
                textKey: 'rail_delivery_text',
                mediaType: 'image',
                media: '/assets/images/rail-bg.jpg',
            },
            {
                id: 'section3',
                titleKey: 'sea_delivery',
                textKey: 'sea_delivery_text',
                mediaType: 'image',
                media: '/assets/images/sea-bg.jpg',
            },
            {
                id: 'section4',
                titleKey: 'air_delivery',
                textKey: 'air_delivery_text',
                mediaType: 'image',
                media: '/assets/images/air-bg.jpg',
            },
        ],
    },
    cases: {
        pageKey: 'cases',
        title: 'Кейсы',
        mode: 'cases',
        sections: [
            {
                id: 'case1',
                titleKey: 'case1_title',
                textKey: 'case1_text',
                media: '/assets/images/case1-bg.jpg',
                stats: [
                    { value: '9 200 км', labelKey: 'distance' },
                    { value: '20 дней', labelKey: 'time' },
                    { value: '39 t', labelKey: 'weight' },
                ],
            },
            {
                id: 'case2',
                titleKey: 'case2_title',
                textKey: 'case2_text',
                media: '/assets/images/case2-bg.jpg',
                stats: [
                    { value: '400 км', labelKey: 'distance' },
                    { value: '4 дня', labelKey: 'time' },
                    { value: '-', labelKey: 'weight' },
                ],
            },
            {
                id: 'case3',
                titleKey: 'case3_title',
                textKey: 'case3_text',
                media: '/assets/images/case3-bg.jpg',
                stats: [
                    { value: '3 400 км', labelKey: 'distance' },
                    { value: '12 дней', labelKey: 'time' },
                    { value: '20%', labelKey: 'savings' },
                ],
            },
            {
                id: 'case4',
                titleKey: 'case4_title',
                textKey: 'case4_text',
                media: '/assets/images/case4-bg.jpg',
                stats: [
                    { value: '-', labelKey: 'distance' },
                    { value: '-30%', labelKey: 'time' },
                    { value: '15%', labelKey: 'savings' },
                ],
            },
        ],
    },
    contacts: {
        pageKey: 'contacts',
        title: 'Контакты',
        mode: 'contacts',
    },
};

export const publicNavigation = [
    { key: 'home', labelKey: 'home', href: '/' },
    { key: 'about', labelKey: 'about', href: '/about' },
    { key: 'services', labelKey: 'services', href: '/services' },
    { key: 'cases', labelKey: 'cases', href: '/cases' },
    { key: 'contacts', labelKey: 'contacts', href: '/contacts' },
];
