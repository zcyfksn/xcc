// XCC TMP EditorDoc.cpp : implementation of the CXCCTMPEditorDoc class
//

#include "stdafx.h"
#include "XCC TMP Editor.h"

#include "XCC TMP EditorDoc.h"

#include "mix_file.h"
#include "pal_file.h"
#include "string_conversion.h"
#include "xcc_dirs.h"

#ifdef _DEBUG
#define new DEBUG_NEW
#undef THIS_FILE
static char THIS_FILE[] = __FILE__;
#endif

/////////////////////////////////////////////////////////////////////////////
// CXCCTMPEditorDoc

IMPLEMENT_DYNCREATE(CXCCTMPEditorDoc, CDocument)

BEGIN_MESSAGE_MAP(CXCCTMPEditorDoc, CDocument)
	//{{AFX_MSG_MAP(CXCCTMPEditorDoc)
	//}}AFX_MSG_MAP
END_MESSAGE_MAP()

/////////////////////////////////////////////////////////////////////////////
// CXCCTMPEditorDoc construction/destruction

CXCCTMPEditorDoc::CXCCTMPEditorDoc()
{
}

CXCCTMPEditorDoc::~CXCCTMPEditorDoc()
{
}

BOOL CXCCTMPEditorDoc::OnNewDocument()
{
	if (!CDocument::OnNewDocument())
		return FALSE;

	m_header.cblocks_x = m_header.cblocks_y = 0;
	m_header.cx = 60;
	m_header.cy = 30;
	m_map.clear();
	load_temperate_palet();

	return TRUE;
}



/////////////////////////////////////////////////////////////////////////////
// CXCCTMPEditorDoc serialization

void CXCCTMPEditorDoc::Serialize(CArchive& ar)
{
	if (ar.IsStoring())
	{
		// m_header.cblocks_x = 0;
		// m_header.cblocks_y = 0;
		int cblocks_x = m_header.cblocks_x;
		int cblocks_y = m_header.cblocks_y;
		int half_cx = m_header.cx / 2;
		int half_cy = m_header.cy / 2;

		byte* d = new byte[256 << 10];
		byte* w = d;

		*reinterpret_cast<t_tmp_ts_header*>(w) = m_header;;
		w += sizeof(t_tmp_ts_header);
		int* index = reinterpret_cast<int*>(w);
		w += 4 * m_header.cblocks_x * m_header.cblocks_y;
		for (int y = 0; y < cblocks_y; y++)
		{
			for (int x = 0; x < cblocks_x; x++)
			{
				for (t_map::iterator i = m_map.begin(); i != m_map.end(); i++)
				{
					t_map_entry& e = i->second;
					if (e.header.x == half_cx * x - half_cx * y && e.header.y == half_cy * x + half_cy * y)
					{
						*index++ = w - d;
						int cb_diamond = m_header.cx * m_header.cy >> 1;
						int cb_extra_data = e.header.cx_extra * e.header.cy_extra;
						e.header.z_ofs = cb_diamond + sizeof(t_tmp_image_header);		
						e.header.extra_ofs = 2 * cb_diamond + sizeof(t_tmp_image_header);
						if (i->second.extra_data.data())
							e.header.extra_z_ofs = 2 * cb_diamond + cb_extra_data + sizeof(t_tmp_image_header);
						e.header.has_extra_data = static_cast<bool>(i->second.extra_data.data());
						e.header.has_z_data = static_cast<bool>(e.z_data.data());
						e.header.has_damaged_data = false;
						*reinterpret_cast<t_tmp_image_header*>(w) = e.header;
						w += sizeof(t_tmp_image_header);
						memcpy(w, e.data.data(), cb_diamond);
						w += cb_diamond;
						if (e.z_data.data())
						{
							memcpy(w, e.z_data.data(), cb_diamond);
							w += cb_diamond;
						}
						if (i->second.extra_data.data())
						{
							memcpy(w, i->second.extra_data.data(), cb_extra_data);
							w += cb_extra_data;
							if (i->second.extra_z_data.data())
							{
								memcpy(w, i->second.extra_z_data.data(), cb_extra_data);
								w += cb_extra_data;
							}
						}
						break;
					}
				}
				if (i == m_map.end())
					*index++ = 0;
			}
		}
		int cb_d = w - d;
		ar.Write(d, cb_d);
		delete[] d;
	}
	else
	{
		int cb_s = ar.GetFile()->GetLength();
		byte* s = new byte[cb_s];
		Ctmp_ts_file f;
		if (ar.Read(s, cb_s) != cb_s)
			AfxThrowArchiveException(CArchiveException::badIndex, ar.m_strFileName);
		f.load(s, cb_s);
		if (!f.is_valid())
			AfxThrowArchiveException(CArchiveException::badIndex, ar.m_strFileName);
		else
		{
			m_header = *f.get_header();
			int cb_diamond = f.get_cb_diamond();
			for (int i = 0; i < f.get_c_tiles(); i++)
			{
				if (!f.get_index()[i])
					continue;
				const t_tmp_image_header& header = *f.get_image_header(i);
				t_map_entry e;
				e.header = header;
				bool has_z_data = header.has_z_data;
				e.data = Cvirtual_binary(f.get_image(i), cb_diamond);
				if (has_z_data)
					e.z_data = Cvirtual_binary(f.get_z_image(i), cb_diamond);
				if (header.has_extra_data)
				{
					int cb_extra_data = f.get_cb_extra_data(i);;
					e.extra_data = Cvirtual_binary(f.get_extra_data(i), cb_extra_data);
					if (has_z_data)
						e.extra_z_data = Cvirtual_binary(f.get_extra_z_data(i), cb_extra_data);
				}
				m_map[m_map.empty() ? 0 : m_map.rbegin()->first + 1] = e;
			}
			string ext = to_lower(Cfname(static_cast<string>(ar.m_strFileName)).get_fext());
			if (ext == ".sno")
				load_snow_palet();
			else if (ext == ".urb")
				load_urban_palet();
			else
				load_temperate_palet();
		}
		delete[] s;
	}
}

int CXCCTMPEditorDoc::load_palet(string fname)
{
	if (Cfname(fname).get_path().size())
	{
		Cpal_file f;
		int error = f.open(fname);
		if (!error && !f.is_valid())
			error = 0x100;
		else
		{
			f.decode(m_palet);
			f.close();
			UpdateAllViews(NULL);
		}
		return error;
	}
	Cmix_file main_mix;
	int error = main_mix.open(xcc_dirs::get_main_mix(m_header.cx == 48 ? game_ts : game_ra2));
	if (!error)
	{
		Cmix_file cache_mix;
		error = cache_mix.open("cache.mix", main_mix);
		if (!error)
		{
			Cpal_file f;
			error = f.open(fname, cache_mix);
			if (!error && !f.is_valid())
				error = 0x100;
			else
			{
				f.decode(m_palet);
				f.close();
				UpdateAllViews(NULL);
			}
			cache_mix.close();
		}
		main_mix.close();
	}
	return error;
}

int CXCCTMPEditorDoc::load_snow_palet()
{
	return load_palet("isosno.pal");
}

int CXCCTMPEditorDoc::load_temperate_palet()
{
	return load_palet("isotem.pal");
}

int CXCCTMPEditorDoc::load_urban_palet()
{
	return load_palet("isourb.pal");
}

/////////////////////////////////////////////////////////////////////////////
// CXCCTMPEditorDoc diagnostics

#ifdef _DEBUG
void CXCCTMPEditorDoc::AssertValid() const
{
	CDocument::AssertValid();
}

void CXCCTMPEditorDoc::Dump(CDumpContext& dc) const
{
	CDocument::Dump(dc);
}
#endif //_DEBUG

/////////////////////////////////////////////////////////////////////////////
// CXCCTMPEditorDoc commands

void CXCCTMPEditorDoc::SetModifiedFlag(BOOL bModified)
{
	if (IsModified() == bModified)
		return;
	CDocument::SetModifiedFlag(bModified);
	CString title = GetTitle();
	if (bModified)
	{
		if (title.Find('*') < 0)
			title += '*';
	}
	else
	{
		if (title.Find('*') >= 0)
			title.Delete(title.GetLength() - 1);
	}
	SetTitle(title);
}

void CXCCTMPEditorDoc::remove(int id)
{
	m_map.erase(id);
	SetModifiedFlag();
	UpdateAllViews(NULL);
}

void CXCCTMPEditorDoc::remove_extra_image(int id)
{
	m_map.find(id)->second.extra_data.clear();
	SetModifiedFlag();
	UpdateAllViews(NULL);
}

Cvirtual_image CXCCTMPEditorDoc::get_complete() const
{
	t_rect global = get_rect();
	int cx = global.r - global.x;
	int cy = global.b - global.y;
	byte* d = new byte[cx * cy];
	draw(d);
	Cvirtual_image image;
	image.load(d, cx, cy, 1, palet());
	delete[] d;
	return image;
}

void CXCCTMPEditorDoc::set_complete(const Cvirtual_image& image)
{
	t_rect global = get_rect();
	int cx = global.r - global.x;
	int cy = global.b - global.y;
	if (image.cx() != cx || image.cy() != cy)
		return;
	draw_reverse(image.image());
}

Cvirtual_image CXCCTMPEditorDoc::get_image(int id)
{
	const t_map_entry& e = map().find(id)->second;
	int cx = header().cx;
	int cy = header().cy;
	byte* d = new byte[cx * cy];
	decode_tile(e.data.data(), d, cx);
	Cvirtual_image image;
	image.load(d, cx, cy, 1, palet());
	delete[] d;
	return image;
}

Cvirtual_image CXCCTMPEditorDoc::get_extra_image(int id)
{
	const t_map_entry& e = map().find(id)->second;
	Cvirtual_image image;
	image.load(e.extra_data.data(), e.header.cx_extra, e.header.cy_extra, 1, palet());
	return image;
}

void CXCCTMPEditorDoc::set_image(int id, const Cvirtual_image& image)
{
	int cx = header().cx;
	int cy = header().cy;
	if (image.cx() != cx || image.cy() != cy)
		return;
	t_map_entry& e = map_edit().find(id)->second;
	encode_tile(image.image(), e.data.data_edit(), cx);
	if (e.header.has_z_data && !e.z_data.data())
		encode_tile(image.image(), e.z_data.data_edit(), cx);
	SetModifiedFlag();
	UpdateAllViews(NULL);
}

void CXCCTMPEditorDoc::set_extra_image(int id, const Cvirtual_image& image)
{
	t_map_entry& e = map_edit().find(id)->second;
	e.extra_data.write(image.image(), image.cb_image());
	if (e.header.has_z_data && (!e.extra_z_data.data() || e.header.cx_extra != image.cx() || e.header.cy_extra != image.cy()))
		e.extra_z_data.write(image.image(), image.cb_image());
	if (!e.header.has_extra_data)
		e.header.x_extra = e.header.y_extra = 0;
	e.header.cx_extra = image.cx();
	e.header.cy_extra = image.cy();
	SetModifiedFlag();
	UpdateAllViews(NULL);
}

void CXCCTMPEditorDoc::set_image_header(int id, const t_tmp_image_header& header)
{
	map_edit().find(id)->second.header = header;
	SetModifiedFlag();
	UpdateAllViews(NULL);
}

t_rect CXCCTMPEditorDoc::get_rect(bool view_true_height) const
{
	int half_cy = m_header.cy / 2;
	t_rect rect;
	if (m_map.empty())
	{
		rect.x = rect.y = 0;
		rect.r = rect.b = 0;
	}
	else
	{
		rect.x = rect.y = INT_MAX;
		rect.r = rect.b = INT_MIN;
		for (t_map::const_iterator i = m_map.begin(); i != m_map.end(); i++)
		{
			const t_tmp_image_header& header = i->second.header;
			t_rect t;
			t.x = header.x;
			t.y = header.y;
			t.r = t.x + m_header.cx;
			t.b = t.y + m_header.cy;
			if (header.has_extra_data)
			{
				t.x = min(t.x, header.x_extra);
				t.y = min(t.y, header.y_extra);
				t.r = max(t.r, header.x_extra + header.cx_extra);
				t.b = max(t.b, header.y_extra + header.cy_extra);
			}
			t.y -= header.height * half_cy;
			t.b -= header.height * half_cy;
			rect.x = min(rect.x, t.x);
			rect.y = min(rect.y, t.y);
			rect.r = max(rect.r, t.r);
			rect.b = max(rect.b, t.b);
		}
	}
	if (view_true_height)
	{
		int y = half_cy * m_header.cblocks_x + half_cy * m_header.cblocks_y;
		rect.b = max(rect.b, y);
	}
	return rect;
}

byte* get_p(byte* d, int x, int y, int cx, int cy)
{
	return d + x + cx * y;
}

const byte* get_p(const byte* d, int x, int y, int cx, int cy)
{
	return d + x + cx * y;
}

void CXCCTMPEditorDoc::draw(byte* d, int outline, bool view_true_height) const
{
	int half_cy = m_header.cy / 2;
	t_rect global = get_rect(view_true_height);
	int global_cx = global.r - global.x;
	int global_cy = global.b - global.y;
	memset(d, 0, global_cx * global_cy);
	for (t_map::const_iterator i = m_map.begin(); i != m_map.end(); i++)
	{
		const t_map_entry& e = i->second;
		const t_tmp_image_header& header = e.header;
		const byte* r = e.data.data();
		byte* w_line = get_p(d, header.x - global.x, header.y - global.y - header.height * half_cy, global_cx, global_cy);
		int x = m_header.cx / 2;
		int cx = 0;
		for (int y = 0; y < half_cy; y++)
		{
			cx += 4;
			x -= 2;
			memcpy(w_line + x, r, cx);
			if (outline == i->first)
			{
				memset(w_line + x, 0xff, 2);
				memset(w_line + x + cx - 2, 0xff, 2);
			}
			r += cx;
			w_line += global_cx;
		}
		for (; y < m_header.cy; y++)
		{
			cx -= 4;
			x += 2;
			memcpy(w_line + x, r, cx);
			if (outline == i->first && cx)
			{
				memset(w_line + x, 0xff, 2);
				memset(w_line + x + cx - 2, 0xff, 2);
			}
			r += cx;
			w_line += global_cx;
		}
		if (e.extra_data.data())
		{
			r = e.extra_data.data();
			byte* w_line = get_p(d, header.x_extra - global.x, header.y_extra - global.y - header.height * half_cy, global_cx, global_cy);
			int cx = header.cx_extra;
			int cy = header.cy_extra;
			for (y = 0; y < cy; y++)
			{
				byte* w = w_line;
				for (int i = 0; i < cx; i++)
				{
					int v = *r++;
					if (v)
						*w = v;
					w++;
				}
				w_line += global_cx;
			}
		}
	}
}

void CXCCTMPEditorDoc::draw_reverse(const byte* d)
{
	int half_cy = m_header.cy / 2;
	t_rect global = get_rect();
	int global_cx = global.r - global.x;
	int global_cy = global.b - global.y;
	for (t_map::iterator i = m_map.begin(); i != m_map.end(); i++)
	{
		t_map_entry& e = i->second;
		t_tmp_image_header& header = e.header;
		byte* r = e.data.data_edit();
		const byte* w_line = get_p(d, header.x - global.x, header.y - global.y - header.height * half_cy, global_cx, global_cy);
		int x = m_header.cx / 2;
		int cx = 0;
		for (int y = 0; y < half_cy; y++)
		{
			cx += 4;
			x -= 2;
			memcpy(r, w_line + x, cx);
			r += cx;
			w_line += global_cx;
		}
		for (; y < m_header.cy; y++)
		{
			cx -= 4;
			x += 2;
			memcpy(r, w_line + x, cx);
			r += cx;
			w_line += global_cx;
		}
		if (e.extra_data.data())
		{
			r = e.extra_data.data_edit();
			const byte* w_line = get_p(d, header.x_extra - global.x, header.y_extra - global.y - header.height * half_cy, global_cx, global_cy);
			int cx = header.cx_extra;
			int cy = header.cy_extra;
			for (y = 0; y < cy; y++)
			{
				const byte* w = w_line;
				for (int i = 0; i < cx; i++)
				{
					int v = *r;
					if (v)
						*r = *w;
					r++;
					w++;
				}
				w_line += global_cx;
			}
		}
	}
	SetModifiedFlag();
	UpdateAllViews(NULL);
}
