(function (window) {
  'use strict';

  const BS_MONTH_DAYS = {
    2000: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2001: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2002: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2003: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2004: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2005: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2006: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2007: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2008: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
    2009: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2010: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2011: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2012: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2013: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2014: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2015: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2016: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2017: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2018: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2019: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2020: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2021: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2022: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2023: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2024: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2025: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2026: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2027: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2028: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2029: [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
    2030: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2031: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2032: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2033: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2034: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2035: [30, 32, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
    2036: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2037: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2038: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2039: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2040: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2041: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2042: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2043: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2044: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2045: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2046: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2047: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2048: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2049: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2050: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2051: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2052: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2053: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2054: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2055: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2056: [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30],
    2057: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2058: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2059: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2060: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2061: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2062: [30, 32, 31, 32, 31, 31, 29, 30, 29, 30, 29, 31],
    2063: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2064: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2065: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2066: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 29, 31],
    2067: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2068: [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2069: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2070: [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30],
    2071: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2072: [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30],
    2073: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
    2074: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2075: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2076: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2077: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    2078: [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
    2079: [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
    2080: [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30],
    2081: [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
    2082: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
    2083: [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
    2084: [31, 31, 32, 31, 31, 30, 30, 30, 29, 30, 30, 30],
    2085: [31, 32, 31, 32, 30, 31, 30, 30, 29, 30, 30, 30],
    2086: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
    2087: [31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30],
    2088: [30, 31, 32, 32, 30, 31, 30, 30, 29, 30, 30, 30],
    2089: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
    2090: [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
    2091: [31, 31, 32, 31, 31, 31, 30, 30, 29, 30, 30, 30],
    2092: [31, 30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30],
  };

  const REF_AD = { year: 1943, month: 4, day: 14 };
  const REF_BS = { year: 2000, month: 1, day: 1 };

  const BS_MONTHS_EN = {
    1: 'Baisakh',
    2: 'Jestha',
    3: 'Ashadh',
    4: 'Shrawan',
    5: 'Bhadra',
    6: 'Ashwin',
    7: 'Kartik',
    8: 'Mangsir',
    9: 'Poush',
    10: 'Magh',
    11: 'Falgun',
    12: 'Chaitra',
  };

  const BS_MONTHS_NE = {
    1: 'बैशाख',
    2: 'जेठ',
    3: 'असार',
    4: 'श्रावण',
    5: 'भदौ',
    6: 'असोज',
    7: 'कार्तिक',
    8: 'मंसिर',
    9: 'पौष',
    10: 'माघ',
    11: 'फागुन',
    12: 'चैत',
  };

  const WEEKDAYS_EN = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const WEEKDAYS_NE = ['आइत', 'सोम', 'मंगल', 'बुध', 'बिही', 'शुक्र', 'शनि'];
  const BS_CACHE = new Map();

  function normalizeLocale(locale) {
    return String(locale || 'en').toLowerCase() === 'ne' ? 'ne' : 'en';
  }

  function toNepaliDigits(value) {
    return String(value ?? '').replace(/[0-9]/g, (digit) => ({
      0: '०',
      1: '१',
      2: '२',
      3: '३',
      4: '४',
      5: '५',
      6: '६',
      7: '७',
      8: '८',
      9: '९',
    }[digit] || digit));
  }

  function parseDate(input) {
    if (input instanceof Date && !Number.isNaN(input.getTime())) {
      return input;
    }

    const parsed = new Date(input);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed;
    }

    return new Date();
  }

  function getKathmanduYmd(dateInput) {
    const date = parseDate(dateInput);
    const out = {};

    try {
      const formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Kathmandu',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      });
      const parts = formatter.formatToParts(date);
      parts.forEach((part) => {
        if (part.type === 'year' || part.type === 'month' || part.type === 'day') {
          out[part.type] = Number(part.value);
        }
      });
    } catch (error) {
      // Keep fallback values below.
    }

    return {
      year: Number(out.year || date.getUTCFullYear()),
      month: Number(out.month || (date.getUTCMonth() + 1)),
      day: Number(out.day || date.getUTCDate()),
    };
  }

  function ymdToDays(year, month, day) {
    return Math.floor(Date.UTC(year, month - 1, day) / 86400000);
  }

  function monthDays(year, month) {
    const yearRow = BS_MONTH_DAYS[year];
    if (!yearRow || month < 1 || month > 12) {
      throw new Error('Nepali date out of supported range.');
    }

    return Number(yearRow[month - 1]);
  }

  function incrementBs(state) {
    const next = {
      year: state.year,
      month: state.month,
      day: state.day + 1,
    };
    if (next.day > monthDays(next.year, next.month)) {
      next.day = 1;
      next.month += 1;
      if (next.month > 12) {
        next.month = 1;
        next.year += 1;
      }
    }
    return next;
  }

  function decrementBs(state) {
    const next = {
      year: state.year,
      month: state.month,
      day: state.day - 1,
    };
    if (next.day < 1) {
      next.month -= 1;
      if (next.month < 1) {
        next.month = 12;
        next.year -= 1;
      }
      next.day = monthDays(next.year, next.month);
    }
    return next;
  }

  function adToBs(input) {
    const ad = getKathmanduYmd(input);
    const cacheKey = [ad.year, ad.month, ad.day].join('-');
    const cached = BS_CACHE.get(cacheKey);
    if (cached) {
      return {
        year: cached.year,
        month: cached.month,
        day: cached.day,
        weekday: cached.weekday,
      };
    }

    const offset = ymdToDays(ad.year, ad.month, ad.day) - ymdToDays(REF_AD.year, REF_AD.month, REF_AD.day);
    let state = {
      year: REF_BS.year,
      month: REF_BS.month,
      day: REF_BS.day,
    };

    if (offset > 0) {
      for (let i = 0; i < offset; i += 1) {
        state = incrementBs(state);
      }
    } else if (offset < 0) {
      for (let i = 0; i < Math.abs(offset); i += 1) {
        state = decrementBs(state);
      }
    }

    const weekday = new Date(Date.UTC(ad.year, ad.month - 1, ad.day)).getUTCDay();

    const result = {
      year: state.year,
      month: state.month,
      day: state.day,
      weekday,
    };
    BS_CACHE.set(cacheKey, result);
    return {
      year: result.year,
      month: result.month,
      day: result.day,
      weekday: result.weekday,
    };
  }

  function formatAd(input, options) {
    const opts = options || {};
    const locale = normalizeLocale(opts.locale);
    const withTime = opts.withTime === true;
    const date = parseDate(input);

    const suffix = locale === 'ne' ? 'ई.सं.' : 'AD';
    try {
      const formatter = new Intl.DateTimeFormat(locale === 'ne' ? 'ne-NP' : 'en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: withTime ? '2-digit' : undefined,
        minute: withTime ? '2-digit' : undefined,
        hour12: withTime ? true : undefined,
        timeZone: 'Asia/Kathmandu',
      });

      return formatter.format(date) + ' ' + suffix;
    } catch (error) {
      return date.toLocaleString(locale === 'ne' ? 'ne-NP' : 'en-US') + ' ' + suffix;
    }
  }

  function formatBs(input, options) {
    const opts = options || {};
    const locale = normalizeLocale(opts.locale);
    const includeWeekday = opts.includeWeekday === true;
    let bs;
    try {
      bs = adToBs(input);
    } catch (error) {
      return '';
    }
    const month = locale === 'ne' ? BS_MONTHS_NE[bs.month] : BS_MONTHS_EN[bs.month];
    const year = locale === 'ne' ? toNepaliDigits(bs.year) : String(bs.year);
    const day = locale === 'ne' ? toNepaliDigits(bs.day) : String(bs.day);
    const suffix = locale === 'ne' ? 'वि.सं.' : 'BS';
    let label = month + ' ' + day + ', ' + year + ' ' + suffix;

    if (includeWeekday) {
      const weekday = locale === 'ne' ? WEEKDAYS_NE[bs.weekday] : WEEKDAYS_EN[bs.weekday];
      label = weekday + ', ' + label;
    }

    return label;
  }

  function dual(input, options) {
    const opts = options || {};
    const locale = normalizeLocale(opts.locale);
    const withTime = opts.withTime === true;
    const ad = formatAd(input, { locale, withTime });
    const bs = formatBs(input, { locale, includeWeekday: !withTime });
    if (!bs) return ad;
    return locale === 'ne' ? (bs + ' | ' + ad) : (ad + ' | ' + bs);
  }

  window.HNepaliDate = {
    adToBs,
    formatAd,
    formatBs,
    dual,
    toNepaliDigits,
  };
}(window));
